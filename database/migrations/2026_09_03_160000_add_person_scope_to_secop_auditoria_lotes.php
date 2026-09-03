<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
            $table->string('filtro_persona')->nullable()->after('criterio_vigencia');
            $table->json('persona_ids')->nullable()->after('filtro_persona');
        });
    }

    public function down(): void
    {
        Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
            $table->dropColumn(['filtro_persona', 'persona_ids']);
        });
    }
};
