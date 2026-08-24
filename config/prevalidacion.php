<?php

return [
    // Solo estos estados del Drive pueden crear o mantener una prevalidación visible en Integra.
    'estados_drive_ingreso' => ['APROBADO'],

    // Se validará y ajustará con los valores reales observados durante el piloto AIM.
    'estados_secop_vigentes' => ['EN EJECUCIÓN', 'EN EJECUCION', 'SUSPENDIDO', 'MODIFICADO'],
];
