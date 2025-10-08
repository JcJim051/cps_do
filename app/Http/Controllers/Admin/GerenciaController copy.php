<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PersonasExport;
use Illuminate\Http\Request;

class PersonaExportController extends Controller
{
    public function export(Request $request)
    {
        $query = Persona::query();

        if ($request->filled('filtro_nivel')) {
            $query->whereHas('nivelAcademico', fn($q) => $q->where('nombre', $request->filtro_nivel));
        }

        if ($request->filled('filtro_estado')) {
            $query->where('estado', $request->filtro_estado);
        }

        return Excel::download(new PersonasExport($query), 'personas.xlsx');
    }
}
