<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SeguimientoFilterRoutesTest extends TestCase
{
    public function test_buscador_y_exportacion_estan_protegidos_por_backpack(): void
    {
        foreach (['seguimiento.fetch-personas', 'seguimiento.export-excel', 'seguimiento.export-executive-excel'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "No existe la ruta {$name}.");
            $this->assertContains('web', $route->middleware());
            $this->assertContains(config('backpack.base.middleware_key', 'admin'), $route->middleware());
        }

        $this->assertSame('admin/seguimiento/export-excel', Route::getRoutes()->getByName('seguimiento.export-excel')->uri());
        $this->assertSame(
            'admin/seguimiento/export-executive-excel',
            Route::getRoutes()->getByName('seguimiento.export-executive-excel')->uri()
        );
    }

    public function test_confirmacion_asistida_secop_esta_protegida_por_backpack(): void
    {
        $route = Route::getRoutes()->getByName('secop.sync.review-confirm');

        $this->assertNotNull($route);
        $this->assertSame('POST', $route->methods()[0]);
        $this->assertContains('web', $route->middleware());
        $this->assertContains(config('backpack.base.middleware_key', 'admin'), $route->middleware());
    }
}
