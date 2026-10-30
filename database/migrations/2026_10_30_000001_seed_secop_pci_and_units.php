<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumns('secretarias', ['codigo_pci_secop', 'nombre_unidad_secop'])) {
            return;
        }

        // Las dependencias centrales comparten PCI; la unidad contratante es
        // el dato que realmente permite diferenciarlas en SECOP II.
        $central = [
            'JUR' => 'JURIDICA',
            'COM' => 'COMUNICACIONES',
            'AGR' => 'AGRICULTURA Y DESARROLLO RURAL',
            'COMP' => 'COMPETITIVIDAD Y DESARROLLO ECONOMICO',
            'SOC' => 'SOCIAL',
            'EDU' => 'EDUCACION',
            'GOB' => 'GOBIERNO Y SEGURIDAD',
            'TIC' => 'TECNOLOGIAS Y SISTEMAS DE INFORMACION',
            'SAL' => 'SALUD',
            'DDHH' => 'DERECHOS HUMANOS Y PAZ',
            'ADMIN' => 'ADMINISTRATIVA',
            'HAC' => 'HACIENDA',
            'DPD' => 'DEPARTAMENTO ADMINISTRATIVO DE PLANEACION DEPARTAMENTAL',
            'FSES' => 'Fondo Social para la Educación Superior',
            'DGRD' => 'Dirección Departamental para la Gestión del Riesgo de Desastres',
            'AMB' => 'AMBIENTE',
            'MYE' => 'MINAS Y ENERGIA',
            'MUJ' => 'MUJER LA FAMILIA Y LA EQUIDAD DE GENERO',
            'VIV' => 'VIVIENDA',
        ];

        foreach ($central as $convencion => $unidad) {
            $this->completeMissing($convencion, '700817075', $unidad);
        }

        // En entidades con NIT propio la unidad puede variar por modalidad,
        // sede o subdirección; el NIT sigue siendo su discriminador principal.
        $ownNitPci = [
            'ITM' => '705645349',
            'ITTM' => '713858108',
            'CC' => '706579125',
            'IDCM' => '705077204',
            'IDER' => '702220054',
            'AIM' => '706520657',
            'ESE' => '700532039',
            'EDESA' => '707273249',
            'LOTERIA' => '700577091',
            'CORMA' => '704015031',
        ];

        foreach ($ownNitPci as $convencion => $pci) {
            $this->completeMissing($convencion, $pci, null);
        }

        $this->completeMissing('HOSP GRAN', '719074353', 'HOSPIGRANADA');
        $this->completeMissing(
            'HOSP VILLAO',
            '702938226',
            'HOSPITAL DEPARTAMENTAL DE VILLAVICENCIO ESE',
        );

        DB::table('secretarias')->where('convencion', 'HOSP GRAN')
            ->whereNull('nombre_secop')
            ->update(['nombre_secop' => 'HOSPITAL DEPARTAMENTAL DE GRANADA E.S.E']);

        // El respaldo tenía el NIT de Granada repetido en Villavicencio. Solo
        // corregimos ese valor conocido; una corrección manual diferente se respeta.
        DB::table('secretarias')->where('convencion', 'HOSP VILLAO')
            ->where(function ($query) {
                $query->whereNull('nit_secop')
                    ->orWhereIn('nit_secop', ['800037021', '8000370217']);
            })
            ->update([
                'nit_secop' => '892000501',
                'nombre_secop' => 'HOSPITAL DEPARTAMENTAL DE VILLAVICENCIO ESE',
            ]);
    }

    public function down(): void
    {
        // No se borran datos operacionales que pudieron corregirse a mano.
    }

    private function completeMissing(string $convencion, string $pci, ?string $unidad): void
    {
        $secretaria = DB::table('secretarias')->where('convencion', $convencion)->first();
        if (! $secretaria) {
            return;
        }

        $changes = [];
        if (blank($secretaria->codigo_pci_secop ?? null)) {
            $changes['codigo_pci_secop'] = $pci;
        }
        if ($unidad !== null && blank($secretaria->nombre_unidad_secop ?? null)) {
            $changes['nombre_unidad_secop'] = $unidad;
        }

        if ($changes !== []) {
            DB::table('secretarias')->where('id', $secretaria->id)->update($changes);
        }
    }
};
