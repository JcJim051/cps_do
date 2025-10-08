<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaestroSeeder extends Seeder
{
    public function run(): void
    {
        // ==============================
        // SECRETARÍAS
        // ==============================
        DB::table('secretarias')->insert([
            ['nombre' => 'SECRETARÍA JURÍDICA', 'convencion' => 'JUR'],
            ['nombre' => 'SECRETARÍA DE COMUNICACIONES', 'convencion' => 'COM'],
            ['nombre' => 'SECRETARÍA DE AGRICULTURA Y DESARROLLO RURAL', 'convencion' => 'AGR'],
            ['nombre' => 'SECRETARÍA DE COMPETITIVIDAD Y DESARROLLO ECONÓMICO', 'convencion' => 'COMP'],
            ['nombre' => 'SECRETARÍA SOCIAL', 'convencion' => 'SOC'],
            ['nombre' => 'SECRETARÍA DE EDUCACIÓN', 'convencion' => 'EDU'],
            ['nombre' => 'SECRETARÍA DE GOBIERNO Y SEGURIDAD', 'convencion' => 'GOB'],
            ['nombre' => 'SECRETARÍA DE TECNOLOGÍAS Y SISTEMAS DE INFORMACIÓN', 'convencion' => 'TIC'],
            ['nombre' => 'SECRETARÍA DE SALUD', 'convencion' => 'SAL'],
            ['nombre' => 'SECRETARÍA DE DERECHOS HUMANOS Y PAZ', 'convencion' => 'DDHH'],
            ['nombre' => 'SECRETARÍA ADMINISTRATIVA', 'convencion' => 'ADMIN'],
            ['nombre' => 'SECRETARÍA DE HACIENDA', 'convencion' => 'HAC'],
            ['nombre' => 'DEPARTAMENTO ADMINISTRATIVO DE PLANEACIÓN', 'convencion' => 'DPD'],
            ['nombre' => 'OFICINA DE CONTROL INTERNO', 'convencion' => 'C INT'],
            ['nombre' => 'OFICINA CONTROL DISCIPLINARIO INTERNO', 'convencion' => 'C INT DISC'],
            ['nombre' => 'DIRECCIÓN PARA EL FOMENTO DE LA EDUCACIÓN SUPERIOR', 'convencion' => 'FSES'],
            ['nombre' => 'DIRECCIÓN PARA LA GESTIÓN DEL RIESGO DE DESASTRES', 'convencion' => 'DGRD'],
            ['nombre' => 'SECRETARÍA DE AMBIENTE', 'convencion' => 'AMB'],
            ['nombre' => 'SECRETARÍA DE MINAS Y ENERGÍA', 'convencion' => 'MYE'],
            ['nombre' => 'SECRETARÍA DE LA MUJER, LA FAMILIA Y LA EQUIDAD DE GÉNERO', 'convencion' => 'MUJ'],
            ['nombre' => 'SECRETARÍA DE VIVIENDA', 'convencion' => 'VIV'],
            ['nombre' => 'TURISMO', 'convencion' => 'ITM'],
            ['nombre' => 'TRÁNSITO', 'convencion' => 'ITTM'],
            ['nombre' => 'CASA DE LA CULTURA', 'convencion' => 'CC'],
            ['nombre' => 'INSTITUTO DE CULTURA', 'convencion' => 'IDCM'],
            ['nombre' => 'IDERMETA', 'convencion' => 'IDER'],
            ['nombre' => 'AIM', 'convencion' => 'AIM'],
        ]);

        // ==============================
        // GERENCIAS
        // ==============================
        DB::table('gerencias')->insert([
            
            ['nombre' => 'GERENCIA DE ASUNTOS JUDICIALES Y CONTENCIOSO ADMINISTRATIVOS', 'convencion' => 'JUR-AJ', 'secretaria_id' =>  1],
            ['nombre' => 'GERENCIA DE ASUNTOS CONTRACTUALES', 'convencion' => 'JUR-AC', 'secretaria_id' =>  1],
            ['nombre' => 'GERENCIA DE CONCEPTOS Y ASISTENCIA JURÍDICA TERRITORIAL', 'convencion' => 'JUR-AT', 'secretaria_id' =>  1],
            ['nombre' => 'GERENCIA DE REDES SOCIALES Y TELEVISIÓN', 'convencion' => 'COM-RST', 'secretaria_id' =>  2],
            ['nombre' => 'GERENCIA DE RADIO', 'convencion' => 'COM-R', 'secretaria_id' =>  2],
            ['nombre' => 'GERENCIA DE DESARROLLO RURAL', 'convencion' => 'AGR-DR', 'secretaria_id' =>  3],
            ['nombre' => 'GERENCIA DE DESARROLLO AGROPECUARIO', 'convencion' => 'AGR-DA', 'secretaria_id' =>  3],
            ['nombre' => 'GERENCIA DE IRACA', 'convencion' => 'AGR-GI', 'secretaria_id' =>  3],
            ['nombre' => 'GERENCIA DE CIENCIA, INNOVACIÓN Y COOPERACIÓN', 'convencion' => 'COMP-IC', 'secretaria_id' =>  4],
            ['nombre' => 'GERENCIA DE INDUSTRIA, EMPLEO Y EMPRENDIMIENTO', 'convencion' => 'COMP-EE', 'secretaria_id' =>  4],
            ['nombre' => 'GERENCIA DE ASUNTOS ÉTNICOS', 'convencion' => 'SOC-AE', 'secretaria_id' =>  5],
            ['nombre' => 'GERENCIA DE INFANCIA, ADOLESCENCIA Y JUVENTUD', 'convencion' => 'SOC-IAJ', 'secretaria_id' =>  5],
            ['nombre' => 'GERENCIA DE ADULTO MAYOR Y PERSONAS EN CONDICIÓN DE DISCAPACIDAD', 'convencion' => 'SOC-ADU', 'secretaria_id' =>  5],
            ['nombre' => 'GERENCIA PLAN DE ALIMENTOS Y NUTRICIÓN', 'convencion' => 'SOC-PAN', 'secretaria_id' =>  5],
            ['nombre' => 'GERENCIA DE COBERTURA', 'convencion' => 'EDU-GC', 'secretaria_id' =>  6],
            ['nombre' => 'GERENCIA DE CALIDAD EDUCATIVA', 'convencion' => 'EDU-EDU', 'secretaria_id' =>  6],
            ['nombre' => 'GERENCIA ADMINISTRATIVA Y FINANCIERA', 'convencion' => 'EDU-AD', 'secretaria_id' =>  6],
            ['nombre' => 'GERENCIA DE SEGURIDAD Y CONVIVENCIA CIUDADANA', 'convencion' => 'GOB-SEG', 'secretaria_id' =>  7],
            ['nombre' => 'GERENCIA DE ACCION COMUNAL Y PARTICIPACION CIUDADANA', 'convencion' => 'GOB-AC', 'secretaria_id' =>  7],
            ['nombre' => 'GERENCIA DE INFRAESTRUCTURA Y SISTEMAS DE INFORMACIÓN', 'convencion' => 'TIC-SI', 'secretaria_id' =>  8],
            ['nombre' => 'GERENCIA DE GOBIERNO DIGITAL', 'convencion' => 'TIC-GD', 'secretaria_id' =>  8],
            ['nombre' => 'GERENCIA DE PRESTACIÓN DE SERVICIOS DE SALUD', 'convencion' => 'SAL-SAL', 'secretaria_id' =>  9],
            ['nombre' => 'GERENCIA DE PROMOCIÓN Y PREVENCIÓN', 'convencion' => 'SAL-PP', 'secretaria_id' =>  9],
            ['nombre' => 'GERENCIA DE CALIDAD, INSPECCIÓN Y VIGILANCIA DE LOS SERVICIOS', 'convencion' => 'SAL-CAL', 'secretaria_id' =>  9],
            ['nombre' => 'GERENCIA ADMINISTRATIVA DE SALUD', 'convencion' => 'SAL-ADM', 'secretaria_id' =>  9],
            ['nombre' => 'GERENCIA DE VICTIMAS', 'convencion' => 'DDHH-V', 'secretaria_id' =>  10],
            ['nombre' => 'GERENCIA DE PROMOCIÓN DE DERECHOS HUMANOS', 'convencion' => 'DDHH-H', 'secretaria_id' =>  10],
            ['nombre' => 'GERENCIA DE TALENTO HUMANO', 'convencion' => 'ADMIN-TH', 'secretaria_id' =>  11],
            ['nombre' => 'GERENCIA DE SERVICIOS ADMINISTRATIVOS', 'convencion' => 'ADMIN-SA', 'secretaria_id' =>  11],
            ['nombre' => 'GERENCIA DE SERVICIO AL CIUDADANO Y GESTIÓN DOCUMENTAL', 'convencion' => 'ADMIN-DOC', 'secretaria_id' =>  11],
            ['nombre' => 'GERENCIA DE DESARROLLO ORGANIZACIONAL', 'convencion' => 'ADMIN-ORG', 'secretaria_id' =>  11],
            ['nombre' => 'GERENCIA DE PRESUPUESTO', 'convencion' => 'HAC-P', 'secretaria_id' =>  12],
            ['nombre' => 'GERENCIA DE CONTADURIA', 'convencion' => 'HAC-C', 'secretaria_id' =>  12],
            ['nombre' => 'GERENCIA DE TESORERÍA', 'convencion' => 'HAC-T', 'secretaria_id' =>  12],
            ['nombre' => 'GERENCIA DE RENTAS', 'convencion' => 'HAC-R', 'secretaria_id' =>  12],
            ['nombre' => 'OFICINA DE PROTOCOLO', 'convencion' => 'HAC-OPR', 'secretaria_id' =>  12],
            ['nombre' => 'GERENCIA DE INFORMACIÓN Y ESTUDIOS ECONÓMICOS', 'convencion' => 'DPD-EE', 'secretaria_id' =>  13],
            ['nombre' => 'GERENCIA DE INVERSIÓN PUBLICA Y BANCO DE PROYECTOS', 'convencion' => 'DPD-BAN', 'secretaria_id' =>  13],
            ['nombre' => 'GERENCIA DE DESARROLLO REGIONAL', 'convencion' => 'DPD-DR', 'secretaria_id' =>  13],
            ['nombre' => 'OFICINA DE CONTROL INTERNO', 'convencion' => 'C INT', 'secretaria_id' =>  14],
            ['nombre' => 'OFICINA CONTROL DISCIPLINARIO INTERNO', 'convencion' => 'C INT DISC', 'secretaria_id' =>  15],
            ['nombre' => 'DIRECCIÓN PARA EL FOMENTO DE LA EDUCACIÓN SUPERIOR', 'convencion' => 'FSES', 'secretaria_id' =>  16],
            ['nombre' => 'DIRECCIÓN PARA LA GESTIÓN DEL RIESGO DE DESASTRES', 'convencion' => 'DGRD', 'secretaria_id' =>  17],
            ['nombre' => 'SECRETARÍA DE AMBIENTE', 'convencion' => 'AMB', 'secretaria_id' =>  18],
            ['nombre' => 'SECRETARÍA DE MINAS Y ENERGÍA', 'convencion' => 'MYE', 'secretaria_id' =>  19],
            ['nombre' => 'SECRETARÍA DE LA MUJER, LA FAMILIA Y LA EQUIDAD DE GÉNERO', 'convencion' => 'MUJ', 'secretaria_id' =>  20],
            ['nombre' => 'SECRETARÍA DE VIVIENDA', 'convencion' => 'VIV', 'secretaria_id' =>  21],
            ['nombre' => 'TURISMO', 'convencion' => 'ITM', 'secretaria_id' =>  22],
            ['nombre' => 'TRÁNSITO', 'convencion' => 'ITTM', 'secretaria_id' =>  23],
            ['nombre' => 'CASA DE LA CULTURA', 'convencion' => 'CC', 'secretaria_id' =>  24],
            ['nombre' => 'INSTITUTO DE CULTURA', 'convencion' => 'IDCM', 'secretaria_id' =>  25],
            ['nombre' => 'IDERMETA', 'convencion' => 'IDER', 'secretaria_id' =>  26],
            ['nombre' => 'AIM', 'convencion' => 'AIM', 'secretaria_id' =>  27],
        ]);

        // ==============================
        // ESTADOS
        // ==============================
        DB::table('estados')->insert([
            ['nombre' => 'CONTRATADO'],
            ['nombre' => 'LIQUIDADO'],
            ['nombre' => 'APROBADO'],
            ['nombre' => 'PENDIENTE APROBACIÓN'],
            ['nombre' => 'VALIDACION HV'],
            ['nombre' => 'CEDE CONTRATO'],
            ['nombre' => 'RECIBE CONTRATO'],
            ['nombre' => 'CAMBIO'],
            ['nombre' => 'NO CONTINUA'],
            ['nombre' => 'NO APROBADO'],
            ['nombre' => 'NO CUMPLE'],
            ['nombre' => 'NO ACEPTA'],
        ]);

        // ==============================
        // NIVELES ACADÉMICOS
        // ==============================
        DB::table('niveles_academicos')->insert([
            ['nombre' => 'BACHILLER'],
            ['nombre' => 'TÉCNICO'],
            ['nombre' => 'TECNÓLOGO'],
            ['nombre' => 'PROFESIONAL'],
            ['nombre' => 'ESPECIALISTA'],
            ['nombre' => 'MAGISTER'],
            ['nombre' => 'DOCTORADO'],
        ]);

        // ==============================
        // EVALUACIONES
        // ==============================
        DB::table('evaluaciones')->insert([
            ['nombre' => 'BUENO'],
            ['nombre' => 'REGULAR'],
            ['nombre' => 'MALO'],
        ]);

        // ==============================
        // FUENTES
        // ==============================
        DB::table('fuentes')->insert([
            ['nombre' => 'REGALIAS'],
            ['nombre' => 'INVERSIÓN'],
            ['nombre' => 'FUNCIONAMIENTO'],
            ['nombre' => 'FOVIM'],
        ]);
        DB::table('estado_personas')->insert([
            ['nombre' => 'CONTRATO'],
            ['nombre' => 'EN PROCESO'],
            ['nombre' => 'PENDIENTE'],
        ]);
        DB::table('tipos')->insert([
            ['nombre' => 'Cps'],
            ['nombre' => 'Libre Nombramiento'],
            ['nombre' => 'Provisional'],
        ]);
    }
}
