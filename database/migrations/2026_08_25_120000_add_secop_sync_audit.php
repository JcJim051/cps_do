<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('secop_vinculos', 'sincronizacion_automatica')) Schema::table('secop_vinculos', function (Blueprint $table) {
            $table->boolean('sincronizacion_automatica')->default(true)->after('ultima_consulta_at');
            $table->json('campos_excluidos')->nullable()->after('sincronizacion_automatica');
            $table->json('origenes_campos')->nullable()->after('campos_excluidos');
            $table->timestamp('ultima_aplicacion_at')->nullable()->after('origenes_campos');
            $table->text('ultimo_error')->nullable()->after('ultima_aplicacion_at');
            $table->json('ultimo_resultado')->nullable()->after('ultimo_error');
        });

        if (!Schema::hasColumn('secop_instantaneas', 'duracion_inicial')) Schema::table('secop_instantaneas', function (Blueprint $table) {
            $table->string('numero_contrato')->nullable()->after('fase');
            $table->decimal('duracion_inicial', 12, 2)->nullable()->after('fecha_fin');
            $table->string('unidad_duracion', 30)->nullable()->after('duracion_inicial');
            $table->unsignedInteger('duracion_inicial_dias')->nullable()->after('unidad_duracion');
            $table->unsignedInteger('dias_adicionados')->nullable()->after('duracion_inicial_dias');
            $table->decimal('meses_adicionados', 12, 2)->nullable()->after('dias_adicionados');
            $table->boolean('marcacion_adicion')->nullable()->after('meses_adicionados');
            $table->timestamp('ultima_actualizacion_fuente')->nullable()->after('marcacion_adicion');
        });

        if (!Schema::hasColumn('seguimientos', 'estado_secop')) Schema::table('seguimientos', function (Blueprint $table) {
            $table->string('estado_secop')->nullable()->after('estado_contrato_id');
            $table->timestamp('ultima_actualizacion_secop')->nullable()->after('estado_secop');
        });

        if (!Schema::hasTable('secop_actualizaciones_seguimientos')) Schema::create('secop_actualizaciones_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->cascadeOnDelete();
            $table->foreignId('vinculo_id')->nullable()->constrained('secop_vinculos')->nullOnDelete();
            $table->foreignId('instantanea_id')->nullable()->constrained('secop_instantaneas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fuente_secop', 20);
            $table->string('identificador_externo');
            $table->string('modo', 20);
            $table->string('resultado', 30);
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->json('campos_aplicados')->nullable();
            $table->json('campos_omitidos')->nullable();
            $table->json('origenes')->nullable();
            $table->json('alertas')->nullable();
            $table->string('hash_aplicacion', 64)->nullable();
            $table->timestamp('aplicado_at');
            $table->timestamps();
            $table->index(['seguimiento_id', 'aplicado_at'], 'secop_actualizaciones_seguimiento_fecha_idx');
            $table->index(['instantanea_id', 'resultado'], 'secop_actualizaciones_inst_result_idx');
        });
        else Schema::table('secop_actualizaciones_seguimientos', function (Blueprint $table) {
            $table->index(['instantanea_id', 'resultado'], 'secop_actualizaciones_inst_result_idx');
        });

        DB::table('estados')->updateOrInsert(
            ['nombre' => 'CANCELADO'],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_actualizaciones_seguimientos');
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropColumn(['estado_secop', 'ultima_actualizacion_secop']);
        });
        Schema::table('secop_instantaneas', function (Blueprint $table) {
            $table->dropColumn([
                'numero_contrato', 'duracion_inicial', 'unidad_duracion', 'duracion_inicial_dias',
                'dias_adicionados', 'meses_adicionados', 'marcacion_adicion', 'ultima_actualizacion_fuente',
            ]);
        });
        Schema::table('secop_vinculos', function (Blueprint $table) {
            $table->dropColumn([
                'sincronizacion_automatica', 'campos_excluidos', 'origenes_campos',
                'ultima_aplicacion_at', 'ultimo_error', 'ultimo_resultado',
            ]);
        });
    }
};
