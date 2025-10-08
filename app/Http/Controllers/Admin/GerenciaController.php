<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gerencia;

class GerenciaController extends Controller
{
    public function getGerenciasPorSecretaria($secretariaId)
    {
        return \App\Models\Gerencia::where('secretaria_id', $secretariaId)
                    ->get(['id', 'nombre'])
                    ->map(fn($g) => ['id' => $g->id, 'text' => $g->nombre]);
    }
}
