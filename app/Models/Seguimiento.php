<?php

    namespace App\Models;
    
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Backpack\CRUD\app\Models\Traits\CrudTrait;
    use Carbon\Carbon;
    
    class Seguimiento extends Model
    {
        use HasFactory;
        use CrudTrait;
    
        protected $table = 'seguimientos';
        protected $casts = [
            'aut_despacho' => 'boolean',
            'aut_planeacion' => 'boolean',
            'aut_administrativa' => 'boolean',
            'aut_despacho_adicion' => 'boolean',
            'aut_planeacion_adicion' => 'boolean',
            'aut_administrativa_adicion' => 'boolean',
            'fecha_aut_despacho' => 'date',
            'fecha_aut_planeacion' => 'date',
            'fecha_aut_administrativa' => 'date',
            'fecha_aut_despacho_adicion' => 'date',
            'fecha_aut_planeacion_adicion' => 'date',
            'fecha_aut_administrativa_adicion' => 'date',
            'fecha_acta_inicio' => 'date',
            'fecha_finalizacion' => 'date',
            'fecha_acta_inicio_adicion' => 'date',
            'fecha_finalizacion_adicion' => 'date',
            'ultima_actualizacion_secop' => 'datetime',
        ];
        protected $fillable = [
            'persona_id',
            'tipo',
            'secretaria_id',
            'gerencia_id',
            'estado_id',
            'observaciones',
            'fecha_entrevista',
            'anio',
            'numero_contrato',
            'fecha_acta_inicio',
            'fecha_finalizacion',
            'tiempo_ejecucion_dias',
            'valor_mensual',
            'valor_total',
            'estado_contrato_id',
            'estado_secop',
            'ultima_actualizacion_secop',
            'aut_despacho',
            'aut_planeacion',
            'aut_administrativa',
            'aut_despacho_adicion',
            'aut_planeacion_adicion',
            'aut_administrativa_adicion',
            'fecha_aut_despacho',
            'fecha_aut_planeacion',
            'fecha_aut_administrativa',
            'fecha_aut_despacho_adicion',
            'fecha_aut_planeacion_adicion',
            'fecha_aut_administrativa_adicion',
            'adicion',
            'fecha_acta_inicio_adicion',
            'fecha_finalizacion_adicion',
            'tiempo_ejecucion_dias_adicion',
            'tiempo_extension_secop_dias',
            'tiempo_suspension_dias',
            'tiempo_total_ejecucion_dias',
            'tiempo_total_calendario_dias',
            'valor_adicion',
            'valor_total_contrato',
            'evaluacion_id',
            'continua',
            'observaciones_contrato',
            'fuente_id',
            'estado_aprobacion',
            'estado_aprobacion_adicion',
        ];
    
        protected bool $skipAutoCalculation = false;
        protected bool $skipSecopExclusions = false;

        public function skipAutoCalculation(bool $value = true): self
        {
            $this->skipAutoCalculation = $value;
            return $this;
        }

        public function skipSecopExclusions(bool $value = true): self
        {
            $this->skipSecopExclusions = $value;
            return $this;
        }
    
        protected static function booted()
        {
            static::saving(function ($seguimiento) {
                if (!$seguimiento->skipAutoCalculation && $seguimiento->tipo === 'contrato' && $seguimiento->estado_contrato_id) {
                    $estado = Estados::query()->find($seguimiento->estado_contrato_id)?->nombre;
                    $estado = mb_strtoupper(trim((string) $estado));

                    if ($estado === 'APROBADO') {
                        $seguimiento->aut_despacho = true;
                        $seguimiento->fecha_aut_despacho ??= Carbon::now('America/Bogota')->toDateString();
                    } elseif (in_array($estado, ['PENDIENTE', 'PENDIENTE APROBACIÓN', 'CAMBIO'], true)) {
                        $seguimiento->aut_despacho = false;
                        $seguimiento->fecha_aut_despacho = null;
                    }
                }
    
                // 🚫 Excel manda → no tocar nada
                if ($seguimiento->skipAutoCalculation) {
                    return;
                }
    
                if ($seguimiento->tipo !== 'contrato') {
                    return;
                }
    
                // --- tiempo ejecución contrato ---
                if (
                    $seguimiento->fecha_acta_inicio &&
                    $seguimiento->fecha_finalizacion &&
                    $seguimiento->tiempo_ejecucion_dias === null
                ) {
                    $inicio = Carbon::parse($seguimiento->fecha_acta_inicio);
                    $fin = Carbon::parse($seguimiento->fecha_finalizacion);
                    $seguimiento->tiempo_ejecucion_dias = $inicio->diffInDays($fin);
                }
    
                // --- Adición ---
                // --- Adición ---
                if ($seguimiento->adicion === 'SI') {

                // calcular solo si Excel NO mandó días
                if (
                $seguimiento->fecha_acta_inicio_adicion &&
                $seguimiento->fecha_finalizacion_adicion &&
                $seguimiento->tiempo_ejecucion_dias_adicion === null
                ) {
                $inicioAd = Carbon::parse($seguimiento->fecha_acta_inicio_adicion);
                $finAd = Carbon::parse($seguimiento->fecha_finalizacion_adicion);
                $seguimiento->tiempo_ejecucion_dias_adicion = $inicioAd->diffInDays($finAd);
                }

                } else {

                // ⚠️ solo limpiar si Excel NO mandó nada
                if ($seguimiento->fecha_acta_inicio_adicion === null) {
                $seguimiento->fecha_finalizacion_adicion = null;
                $seguimiento->tiempo_ejecucion_dias_adicion = 0;
                $seguimiento->valor_adicion = 0;
                }
                }

    
                // --- total días ---
                $seguimiento->tiempo_total_ejecucion_dias =
                    (int) ($seguimiento->tiempo_ejecucion_dias ?? 0) +
                    (int) ($seguimiento->tiempo_ejecucion_dias_adicion ?? 0);
    
                // --- valor total ---
                $seguimiento->valor_total_contrato =
                    (float) ($seguimiento->valor_total ?? 0) +
                    (float) ($seguimiento->valor_adicion ?? 0);
            });

            static::saved(function ($seguimiento) {
                if ($seguimiento->skipSecopExclusions) return;
                $link = $seguimiento->vinculoSecop()->first();
                if (!$link) return;
                $dirty = array_values(array_intersect(
                    array_keys($seguimiento->getChanges()),
                    \App\Services\SecopAplicacionService::MANAGED_FIELDS,
                ));
                if ($dirty === []) return;
                $excluded = array_values(array_unique(array_merge($link->campos_excluidos ?? [], $dirty)));
                $origins = $link->origenes_campos ?? [];
                foreach ($dirty as $field) $origins[$field] = 'Manual';
                $link->forceFill(['campos_excluidos' => $excluded, 'origenes_campos' => $origins])->save();
            });
        }
    


        public function persona()
        {
            return $this->belongsTo(Persona::class);
        }

        public function evaluacion()
        {
            return $this->belongsTo(Evaluacion::class);
        }
        
        public function nivelAcademico()
        {
            return $this->belongsTo(NivelAcademico::class);
        }
        
        public function estado()
        {
            return $this->belongsTo(Estados::class);
        }
        
        public function secretaria()
        {
            return $this->belongsTo(Secretaria::class);
        }
        public function fuente()
        {
            return $this->belongsTo(Fuente::class);
        }
        
        public function gerencia()
        {
            return $this->belongsTo(Gerencia::class);
        }
        public function estadoContrato()
        {
            return $this->belongsTo(Estados::class, 'estado_contrato_id');
        }

        public function vinculoSecop()
        {
            return $this->hasOne(SecopVinculo::class, 'seguimiento_id');
        }

        public function prevalidacionOrigen()
        {
            return $this->hasOne(PrevalidacionContractual::class, 'seguimiento_id');
        }

        public function actualizacionesSecop()
        {
            return $this->hasMany(SecopActualizacionSeguimiento::class, 'seguimiento_id');
        }
        
    }
    
