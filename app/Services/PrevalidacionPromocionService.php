<?php

namespace App\Services;

use App\Models\EstadoPersona;
use App\Models\Estados;
use App\Models\Persona;
use App\Models\PrevalidacionContractual;
use App\Models\PrevalidacionEvento;
use App\Models\Seguimiento;
use App\Models\Tipo;
use Illuminate\Support\Facades\DB;

class PrevalidacionPromocionService
{
    public function __construct(private PrevalidacionSyncService $sync)
    {
    }

    public function promover(PrevalidacionContractual $record, ?int $userId): Seguimiento
    {
        if ($record->estado !== 'APROBADO') {
            throw new \RuntimeException('Solo se pueden enviar registros aprobados.');
        }
        if ($record->seguimiento_id) {
            $this->syncDrive($record, $userId);
            return $record->seguimiento;
        }
        if (!$record->cedula_o_nit || !$record->nombre_contratista || !$record->secretaria_id) {
            throw new \RuntimeException('Se requieren cédula, nombre y dependencia homologada.');
        }

        $this->sync->assertReadyForTracking($record);

        $seguimiento = DB::transaction(function () use ($record, $userId) {
            $persona = Persona::query()->where('cedula_o_nit', $record->cedula_o_nit)->lockForUpdate()->first();
            if (!$persona) {
                $persona = Persona::create([
                    'cedula_o_nit' => $record->cedula_o_nit,
                    'nombre_contratista' => $record->nombre_contratista,
                    'secretaria_id' => $record->secretaria_id,
                    'gerencia_id' => $record->gerencia_id,
                    'estado_persona_id' => EstadoPersona::query()->where('nombre', 'EN PROCESO')->value('id'),
                    'tipos_id' => Tipo::query()->whereRaw('LOWER(nombre) = ?', ['cps'])->value('id'),
                    'created_by_user_id' => $userId,
                ]);
            }

            $approvedState = Estados::query()->whereRaw('UPPER(nombre) = ?', ['APROBADO'])->value('id');
            if (!$approvedState) {
                throw new \RuntimeException('No existe el estado APROBADO en el catálogo de estados.');
            }

            $seguimiento = Seguimiento::create([
                'persona_id' => $persona->id,
                'tipo' => 'contrato',
                'secretaria_id' => $record->secretaria_id,
                'gerencia_id' => $record->gerencia_id,
                'estado_contrato_id' => $approvedState,
                'anio' => $record->anio ?: ($record->fecha_inicio_planeada?->year ?: now()->year),
                'numero_contrato' => $record->numero_contrato_planeado,
                'fecha_acta_inicio' => $record->fecha_inicio_planeada,
                'fecha_finalizacion' => $record->fecha_fin_planeada,
                'tiempo_ejecucion_dias' => $record->tiempo_planeado_dias,
                'valor_mensual' => $record->valor_mensual_planeado,
                'valor_total' => $record->valor_total_planeado,
                'adicion' => $record->adicion,
                'fecha_acta_inicio_adicion' => $record->fecha_inicio_adicion,
                'fecha_finalizacion_adicion' => $record->fecha_fin_adicion,
                'tiempo_ejecucion_dias_adicion' => $record->tiempo_adicion_dias,
                'tiempo_total_ejecucion_dias' => $record->tiempo_total_dias,
                'valor_adicion' => $record->valor_adicion,
                'valor_total_contrato' => $record->valor_total_contrato ?: $record->valor_total_planeado,
                'observaciones_contrato' => $record->observaciones,
                'aut_despacho' => true,
                'fecha_aut_despacho' => ($record->aprobado_at ?: now())->toDateString(),
            ]);

            $record->update([
                'persona_id' => $persona->id,
                'seguimiento_id' => $seguimiento->id,
                'etapa' => 'EN_SEGUIMIENTO',
                'promovido_at' => now(),
                'promovido_por' => $userId,
            ]);
            $record->vinculoSecop?->update(['seguimiento_id' => $seguimiento->id]);

            PrevalidacionEvento::create([
                'prevalidacion_id' => $record->id,
                'user_id' => $userId,
                'tipo' => 'promocion',
                'despues' => ['persona_id' => $persona->id, 'seguimiento_id' => $seguimiento->id],
            ]);

            return $seguimiento;
        });

        $this->syncDrive($record->fresh('fuente'), $userId);

        return $seguimiento;
    }

    private function syncDrive(PrevalidacionContractual $record, ?int $userId): void
    {
        try {
            $this->sync->moveToTracking($record, $userId);
        } catch (\Throwable $e) {
            $record->update([
                'presente_en_origen' => false,
                'drive_sincronizacion_pendiente' => true,
                'drive_ultimo_error' => mb_substr($e->getMessage(), 0, 65000),
            ]);
        }
    }
}
