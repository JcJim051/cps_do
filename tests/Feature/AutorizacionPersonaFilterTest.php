<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AutorizacionCrudController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutorizacionPersonaFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_contratista');
            $table->string('cedula_o_nit')->nullable();
            $table->timestamps();
        });

        DB::table('personas')->insert([
            'id' => 1,
            'nombre_contratista' => 'Persona de prueba',
            'cedula_o_nit' => '1.030.590.916',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_buscador_remoto_encuentra_documento_aunque_tenga_separadores(): void
    {
        $request = Request::create('/admin/autorizacion/fetch-persona', 'GET', [
            'q' => '1030590916',
        ]);

        $result = (new AutorizacionCrudController())->fetchPersonaFilter($request);

        $this->assertCount(1, $result->items());
        $this->assertSame(1, $result->items()[0]->id);
        $this->assertStringContainsString('1.030.590.916', $result->items()[0]->display_name);
    }
}
