<?php

namespace Tests\Unit;

use App\Services\SecopNormalizer;
use PHPUnit\Framework\TestCase;

class SecopNormalizerTest extends TestCase
{
    public function test_normaliza_formatos_equivalentes_de_contrato(): void
    {
        $normalizer = new SecopNormalizer();

        $this->assertSame('59|2026', $normalizer->contrato('59-2026', 2026)['clave']);
        $this->assertSame('59|2026', $normalizer->contrato('CONTRATO 059 DE 2026')['clave']);
        $this->assertSame('2007|2024', $normalizer->contrato('2007', 2024)['clave']);
        $this->assertSame('2007|2024', $normalizer->contrato('2007 DE 2024')['clave']);
    }

    public function test_nit_coincide_con_o_sin_digito_de_verificacion(): void
    {
        $normalizer = new SecopNormalizer();

        $this->assertTrue($normalizer->nitCoincide('900220547-5', '900220547'));
        $this->assertTrue($normalizer->nitCoincide('9002205475', '900220547'));
        $this->assertFalse($normalizer->nitCoincide('9002205475', '8920001488'));
    }
}
