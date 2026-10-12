<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ProgramaMunicipios
{
    public const ALL = [
        'Acacías', 'Barranca de Upía', 'Cabuyaro', 'Castilla la Nueva', 'Cubarral',
        'Cumaral', 'El Calvario', 'El Castillo', 'El Dorado', 'Fuente de Oro',
        'Granada', 'Guamal', 'La Macarena', 'Uribe', 'Lejanías', 'Mapiripán',
        'Mesetas', 'Puerto Concordia', 'Puerto Gaitán', 'Puerto Lleras', 'Puerto López',
        'Puerto Rico', 'Restrepo', 'San Carlos de Guaroa', 'San Juan de Arama',
        'San Juanito', 'San Martín', 'Villavicencio', 'Vista Hermosa',
    ];

    public static function canonical(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return null;
        }

        foreach (self::ALL as $municipio) {
            if (self::normalize($municipio) === $normalized) {
                return $municipio;
            }
        }

        return null;
    }

    public static function normalize(mixed $value): string
    {
        return Str::upper(Str::ascii(preg_replace('/\s+/u', ' ', trim((string) $value))));
    }
}
