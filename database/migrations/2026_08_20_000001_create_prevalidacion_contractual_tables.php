<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('account_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prevalidacion_fuentes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('secretaria_id')->nullable()->constrained('secretarias')->nullOnDelete();
            $table->string('nit_entidad', 30)->nullable();
            $table->string('spreadsheet_id');
            $table->string('spreadsheet_url')->nullable();
            $table->string('hoja');
            $table->unsignedInteger('fila_encabezados')->default(1);
            $table->json('mapeo_columnas');
            $table->string('columna_clave')->nullable();
            $table->string('columna_estado')->default('ESTADO');
            $table->string('columna_editado')->default('EDITADO_EN_INTEGRA');
            $table->boolean('activa')->default(true);
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
            $table->unique(['spreadsheet_id', 'hoja'], 'preval_fuente_hoja_unique');
        });

        Schema::create('prevalidaciones_contractuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuente_id')->constrained('prevalidacion_fuentes')->cascadeOnDelete();
            $table->string('clave_externa');
            $table->unsignedInteger('fila_origen')->nullable();
            $table->string('cedula_o_nit', 50);
            $table->string('nombre_contratista')->nullable();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('secretaria_id')->nullable()->constrained('secretarias')->nullOnDelete();
            $table->foreignId('gerencia_id')->nullable()->constrained('gerencias')->nullOnDelete();
            $table->string('dependencia_origen')->nullable();
            $table->string('programa_origen')->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('numero_contrato_planeado')->nullable();
            $table->date('fecha_inicio_planeada')->nullable();
            $table->date('fecha_fin_planeada')->nullable();
            $table->integer('tiempo_planeado_dias')->nullable();
            $table->decimal('valor_mensual_planeado', 15, 2)->nullable();
            $table->decimal('valor_total_planeado', 15, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->json('datos_origen')->nullable();
            $table->json('campos_locales')->nullable();
            $table->json('conflictos')->nullable();
            $table->string('hash_origen', 64)->nullable();
            $table->boolean('estado_gestionado_localmente')->default(false);
            $table->boolean('editado_en_integra')->default(false);
            $table->boolean('presente_en_origen')->default(true);
            $table->timestamp('aprobado_at')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seguimiento_id')->nullable()->unique()->constrained('seguimientos')->nullOnDelete();
            $table->timestamp('promovido_at')->nullable();
            $table->foreignId('promovido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->timestamps();
            $table->unique(['fuente_id', 'clave_externa'], 'preval_fuente_clave_unique');
            $table->index(['estado', 'seguimiento_id']);
            $table->index('cedula_o_nit');
        });

        Schema::create('secop_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prevalidacion_id')->nullable()->unique()->constrained('prevalidaciones_contractuales')->nullOnDelete();
            $table->foreignId('seguimiento_id')->nullable()->unique()->constrained('seguimientos')->nullOnDelete();
            $table->string('fuente_secop', 20);
            $table->string('tipo_registro', 20)->default('contrato');
            $table->string('identificador_externo');
            $table->string('id_proceso')->nullable();
            $table->string('referencia_proceso')->nullable();
            $table->string('referencia_contrato')->nullable();
            $table->string('nit_entidad', 30)->nullable();
            $table->timestamp('vinculado_at');
            $table->foreignId('vinculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultima_consulta_at')->nullable();
            $table->timestamps();
            $table->unique(['fuente_secop', 'identificador_externo'], 'secop_identificador_unique');
        });

        Schema::create('secop_instantaneas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vinculo_id')->constrained('secop_vinculos')->cascadeOnDelete();
            $table->string('estado')->nullable();
            $table->string('fase')->nullable();
            $table->date('fecha_firma')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('valor_contrato', 18, 2)->nullable();
            $table->decimal('valor_adiciones', 18, 2)->nullable();
            $table->decimal('valor_total', 18, 2)->nullable();
            $table->string('entidad')->nullable();
            $table->text('objeto')->nullable();
            $table->text('url')->nullable();
            $table->json('datos')->nullable();
            $table->string('hash', 64);
            $table->timestamp('consultado_at');
            $table->timestamps();
            $table->index(['vinculo_id', 'consultado_at']);
        });

        Schema::create('prevalidacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prevalidacion_id')->constrained('prevalidaciones_contractuales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->text('detalle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prevalidacion_eventos');
        Schema::dropIfExists('secop_instantaneas');
        Schema::dropIfExists('secop_vinculos');
        Schema::dropIfExists('prevalidaciones_contractuales');
        Schema::dropIfExists('prevalidacion_fuentes');
        Schema::dropIfExists('google_integrations');
    }
};
