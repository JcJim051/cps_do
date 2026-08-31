<?php

namespace App\Services;

use App\Models\PrevalidacionFuente;
use App\Models\Seguimiento;

class SecopEntidadService
{
    public function nitParaSeguimiento(Seguimiento $seguimiento): ?string
    {
        $seguimiento->loadMissing(['secretaria', 'prevalidacionOrigen.fuente']);

        return $seguimiento->secretaria?->nit_secop
            ?: $seguimiento->prevalidacionOrigen?->fuente?->nit_entidad
            ?: PrevalidacionFuente::query()
                ->where('secretaria_id', $seguimiento->secretaria_id)
                ->where('activa', true)
                ->value('nit_entidad');
    }
}
