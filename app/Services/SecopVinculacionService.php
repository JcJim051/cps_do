<?php

namespace App\Services;

use App\Models\PrevalidacionContractual;
use App\Models\PrevalidacionEvento;
use App\Models\PrevalidacionFuente;
use App\Models\SecopInstantanea;
use App\Models\SecopVinculo;
use App\Models\Seguimiento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SecopVinculacionService
{
    public function __construct(private DatosAbiertosSecopService $secop)
    {
    }

    public function candidatos(PrevalidacionContractual $record): array
    {
        $used = SecopVinculo::query()->get(['fuente_secop', 'identificador_externo'])
            ->mapWithKeys(fn ($link) => [$link->fuente_secop.'|'.$link->identificador_externo => true]);

        return collect($this->secop->consultarCandidatos($record->cedula_o_nit, $record->fuente->nit_entidad))
            ->map(function (array $candidate) use ($record, $used) {
                $key = ($candidate['fuente_codigo'] ?? '').'|'.($candidate['identificador_externo'] ?? '');
                $candidate['disponible'] = !isset($used[$key]);
                $candidate['puntaje'] = $this->score($record, $candidate);
                return $candidate;
            })
            ->sortByDesc('puntaje')->values()->all();
    }

    public function vincular(PrevalidacionContractual $record, string $fuente, string $identificador, ?int $userId): SecopVinculo
    {
        $candidate = $this->secop->buscarCandidato($record->cedula_o_nit, $record->fuente->nit_entidad, $fuente, $identificador);
        if (!$candidate) {
            throw new \RuntimeException('El registro SECOP ya no está disponible entre los candidatos válidos.');
        }

        try {
            return DB::transaction(function () use ($record, $candidate, $fuente, $identificador, $userId) {
                $record->vinculoSecop?->delete();
                $link = SecopVinculo::create([
                    'prevalidacion_id' => $record->id,
                    'seguimiento_id' => $record->seguimiento_id,
                    'fuente_secop' => $fuente,
                    'tipo_registro' => $candidate['tipo_registro'] ?? 'contrato',
                    'identificador_externo' => $identificador,
                    'id_proceso' => $candidate['id_proceso'] ?? $candidate['proceso_de_compra'] ?? null,
                    'referencia_proceso' => $candidate['referencia_proceso'] ?? null,
                    'referencia_contrato' => $candidate['referencia_contrato'] ?? null,
                    'nit_entidad' => $candidate['nit_entidad'] ?? null,
                    'vinculado_at' => now(),
                    'vinculado_por' => $userId,
                    'ultima_consulta_at' => now(),
                ]);
                $this->snapshot($link, $candidate);
                PrevalidacionEvento::create([
                    'prevalidacion_id' => $record->id,
                    'user_id' => $userId,
                    'tipo' => 'vinculo_secop',
                    'despues' => ['fuente' => $fuente, 'identificador' => $identificador],
                ]);
                return $link;
            });
        } catch (QueryException $e) {
            throw new \RuntimeException('Ese contrato SECOP ya está vinculado a otro registro.', previous: $e);
        }
    }

    public function candidatosSeguimiento(Seguimiento $seguimiento): array
    {
        $source = PrevalidacionFuente::query()->where('secretaria_id', $seguimiento->secretaria_id)->where('activa', true)->first();
        if (!$source) {
            throw new \RuntimeException('No hay una fuente activa con NIT configurado para esta secretaría.');
        }
        $used = SecopVinculo::query()->get(['fuente_secop', 'identificador_externo'])
            ->mapWithKeys(fn ($link) => [$link->fuente_secop.'|'.$link->identificador_externo => true]);
        return collect($this->secop->consultarCandidatos($seguimiento->persona->cedula_o_nit, $source->nit_entidad))
            ->map(function (array $candidate) use ($seguimiento, $used) {
                $key = ($candidate['fuente_codigo'] ?? '').'|'.($candidate['identificador_externo'] ?? '');
                $candidate['disponible'] = !isset($used[$key]);
                $candidate['puntaje'] = $this->scoreSeguimiento($seguimiento, $candidate);
                return $candidate;
            })->sortByDesc('puntaje')->values()->all();
    }

    public function vincularSeguimiento(Seguimiento $seguimiento, string $fuente, string $identificador, ?int $userId): SecopVinculo
    {
        $source = PrevalidacionFuente::query()->where('secretaria_id', $seguimiento->secretaria_id)->where('activa', true)->firstOrFail();
        $candidate = $this->secop->buscarCandidato($seguimiento->persona->cedula_o_nit, $source->nit_entidad, $fuente, $identificador);
        if (!$candidate) {
            throw new \RuntimeException('El registro SECOP ya no está disponible entre los candidatos válidos.');
        }
        try {
            return DB::transaction(function () use ($seguimiento, $candidate, $fuente, $identificador, $userId) {
                $seguimiento->vinculoSecop?->delete();
                $link = SecopVinculo::create([
                    'seguimiento_id' => $seguimiento->id,
                    'fuente_secop' => $fuente,
                    'tipo_registro' => $candidate['tipo_registro'] ?? 'contrato',
                    'identificador_externo' => $identificador,
                    'id_proceso' => $candidate['id_proceso'] ?? null,
                    'referencia_proceso' => $candidate['referencia_proceso'] ?? null,
                    'referencia_contrato' => $candidate['referencia_contrato'] ?? null,
                    'nit_entidad' => $candidate['nit_entidad'] ?? null,
                    'vinculado_at' => now(), 'vinculado_por' => $userId, 'ultima_consulta_at' => now(),
                ]);
                $this->snapshot($link, $candidate);
                return $link;
            });
        } catch (QueryException $e) {
            throw new \RuntimeException('Ese contrato SECOP ya está vinculado a otro seguimiento.', previous: $e);
        }
    }

    public function desvincular(PrevalidacionContractual $record, ?int $userId): void
    {
        $link = $record->vinculoSecop;
        if (!$link) {
            return;
        }
        PrevalidacionEvento::create([
            'prevalidacion_id' => $record->id,
            'user_id' => $userId,
            'tipo' => 'desvinculo_secop',
            'antes' => ['fuente' => $link->fuente_secop, 'identificador' => $link->identificador_externo],
        ]);
        $link->delete();
    }

    public function refrescar(SecopVinculo $link): bool
    {
        $owner = $link->prevalidacion ?: $link->seguimiento?->prevalidacionOrigen;
        $seguimiento = $link->seguimiento;
        if (!$owner && !$seguimiento) {
            return false;
        }
        $document = $owner?->cedula_o_nit ?: $seguimiento?->persona?->cedula_o_nit;
        $nit = $owner?->fuente?->nit_entidad ?: PrevalidacionFuente::query()
            ->where('secretaria_id', $seguimiento?->secretaria_id)->where('activa', true)->value('nit_entidad');
        if (!$document || !$nit) {
            return false;
        }
        $candidates = $this->secop->consultarCandidatos($document, $nit);
        $candidate = collect($candidates)->first(fn (array $row) =>
            ($row['fuente_codigo'] ?? '') === $link->fuente_secop
            && (string) ($row['identificador_externo'] ?? '') === $link->identificador_externo
        );

        if ($link->tipo_registro === 'proceso') {
            $contract = collect($candidates)->first(fn (array $row) =>
                ($row['tipo_registro'] ?? '') === 'contrato'
                && ($row['id_proceso'] ?? null) === $link->id_proceso
            );
            if ($contract && !SecopVinculo::query()
                ->where('fuente_secop', $contract['fuente_codigo'])
                ->where('identificador_externo', $contract['identificador_externo'])
                ->whereKeyNot($link->id)->exists()) {
                $candidate = $contract;
                $link->fill([
                    'fuente_secop' => $contract['fuente_codigo'],
                    'tipo_registro' => 'contrato',
                    'identificador_externo' => $contract['identificador_externo'],
                    'referencia_contrato' => $contract['referencia_contrato'] ?? null,
                ]);
            }
        }

        if (!$candidate) {
            $link->update(['ultima_consulta_at' => now()]);
            return false;
        }

        $link->ultima_consulta_at = now();
        $link->save();
        return $this->snapshot($link, $candidate);
    }

    public function snapshot(SecopVinculo $link, array $data): bool
    {
        $normalized = [
            'estado' => $data['estado'] ?? null,
            'fase' => $data['fase'] ?? null,
            'fecha_firma' => $data['fecha_firma'] ?? null,
            'fecha_inicio' => $data['fecha_inicio'] ?? null,
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'valor_contrato' => $this->number($data['valor_contrato'] ?? null),
            'valor_adiciones' => $this->number($data['valor_adiciones'] ?? null),
            'valor_total' => $this->number($data['valor_total_con_adiciones'] ?? $data['valor_contrato'] ?? null),
            'entidad' => $data['nombre_entidad'] ?? null,
            'objeto' => $data['objeto'] ?? null,
            'url' => $data['url'] ?? null,
        ];
        $hash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
        if ($link->instantaneas()->latest('consultado_at')->value('hash') === $hash) {
            return false;
        }
        SecopInstantanea::create($normalized + [
            'vinculo_id' => $link->id,
            'datos' => $data,
            'hash' => $hash,
            'consultado_at' => now(),
        ]);
        return true;
    }

    private function score(PrevalidacionContractual $record, array $candidate): int
    {
        $score = 0;
        if ($record->fuente->nit_entidad && $this->digits($record->fuente->nit_entidad) === $this->digits($candidate['nit_entidad'] ?? '')) {
            $score += 50;
        }
        if ($record->numero_contrato_planeado && str_contains(
            mb_strtoupper((string) ($candidate['referencia_contrato'] ?? $candidate['referencia_proceso'] ?? '')),
            mb_strtoupper($record->numero_contrato_planeado)
        )) {
            $score += 35;
        }
        if ($record->anio && str_starts_with((string) ($candidate['fecha_firma'] ?? $candidate['fecha_publicacion'] ?? ''), (string) $record->anio)) {
            $score += 10;
        }
        $planned = (float) $record->valor_total_planeado;
        $observed = (float) ($candidate['valor_total_con_adiciones'] ?? 0);
        if ($planned > 0 && $observed > 0 && abs($planned - $observed) / $planned <= .03) {
            $score += 20;
        }
        if (($candidate['tipo_registro'] ?? '') === 'contrato') {
            $score += 5;
        }
        return min($score, 100);
    }

    private function scoreSeguimiento(Seguimiento $record, array $candidate): int
    {
        $score = ($candidate['tipo_registro'] ?? '') === 'contrato' ? 5 : 0;
        if ($record->numero_contrato && str_contains(
            mb_strtoupper((string) ($candidate['referencia_contrato'] ?? $candidate['referencia_proceso'] ?? '')),
            mb_strtoupper($record->numero_contrato)
        )) $score += 50;
        if ($record->anio && str_starts_with((string) ($candidate['fecha_firma'] ?? $candidate['fecha_publicacion'] ?? ''), (string) $record->anio)) $score += 15;
        $planned = (float) ($record->valor_total_contrato ?: $record->valor_total);
        $observed = (float) ($candidate['valor_total_con_adiciones'] ?? 0);
        if ($planned > 0 && $observed > 0 && abs($planned - $observed) / $planned <= .03) $score += 25;
        return min($score, 100);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
