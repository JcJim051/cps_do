<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretarias', function (Blueprint $table) {
            $table->boolean('es_descentralizada')->default(false)->after('auditoria_secop_incluida')->index();
        });

        $knownNames = [
            'AIM',
            'IAM',
            'TURISMO',
            'INSTITUTO DEPARTAMENTAL DE TURISMO DEL META',
            'IDERMETA',
            'INSTITUTO DE DEPORTE Y RECREACION DEL META',
            'LOTERIA DEL META',
            'ESE',
            'INSTITUTO DE CULTURA',
            'INSTITUTO DE CULTURA DEL META',
            'CASA DE LA CULTURA',
        ];

        DB::table('secretarias')
            ->select(['id', 'nombre'])
            ->orderBy('id')
            ->get()
            ->filter(fn ($secretaria) => in_array(
                Str::upper(Str::ascii(trim((string) $secretaria->nombre))),
                $knownNames,
                true
            ))
            ->each(fn ($secretaria) => DB::table('secretarias')
                ->where('id', $secretaria->id)
                ->update(['es_descentralizada' => true]));
    }

    public function down(): void
    {
        Schema::table('secretarias', function (Blueprint $table) {
            $table->dropIndex(['es_descentralizada']);
            $table->dropColumn('es_descentralizada');
        });
    }
};
