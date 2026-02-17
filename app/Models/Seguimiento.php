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
            'fecha_aut_despacho' => 'date',
            'fecha_aut_planeacion' => 'date',
            'fecha_aut_administrativa' => 'date',
            'fecha_acta_inicio' => 'date',
            'fecha_finalizacion' => 'date',
            'fecha_acta_inicio_adicion' => 'date',
            'fecha_finalizacion_adicion' => 'date',
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
            'aut_despacho',
            'aut_planeacion',
            'aut_administrativa',
            'fecha_aut_despacho',
            'fecha_aut_planeacion',
            'fecha_aut_administrativa',
            'adicion',
            'fecha_acta_inicio_adicion',
            'fecha_finalizacion_adicion',
            'tiempo_ejecucion_dias_adicion',
            'tiempo_total_ejecucion_dias',
            'valor_adicion',
            'valor_total_contrato',
            'evaluacion_id',
            'continua',
            'observaciones_contrato',
            'fuente_id'
        ];
    
        protected bool $skipAutoCalculation = false;

        public function skipAutoCalculation(bool $value = true): self
        {
            $this->skipAutoCalculation = $value;
            return $this;
        }
    
        protected static function booted()
        {
            static::saving(function ($seguimiento) {
    
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
        
    }
    