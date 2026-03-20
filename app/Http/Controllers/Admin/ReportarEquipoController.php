<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipoCampania;
use App\Models\Persona;
use App\Models\NivelAcademico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportarEquipoController extends Controller
{
    public function index(Request $request)
    {
        $user = backpack_user();
        $equipos = EquipoCampania::with('ejercicioPolitico')
            ->where('coordinador_user_id', $user->id)
            ->get();

        $equipoId = $request->get('equipo_id');
        $equipoSeleccionado = $equipos->firstWhere('id', (int) $equipoId);

        $listado = collect();
        if ($equipoSeleccionado) {
            $listado = $equipoSeleccionado->personas()->get()->map(function ($persona) use ($equipoSeleccionado) {
                return [
                    'equipo' => $equipoSeleccionado->nombre,
                    'equipo_id' => $equipoSeleccionado->id,
                    'campania' => $equipoSeleccionado->ejercicioPolitico?->nombre,
                    'persona' => $persona,
                ];
            });
        } else {
            foreach ($equipos as $equipo) {
                $equipo->personas()->get()->each(function ($persona) use ($equipo, &$listado) {
                    $listado->push([
                        'equipo' => $equipo->nombre,
                        'equipo_id' => $equipo->id,
                        'campania' => $equipo->ejercicioPolitico?->nombre,
                        'persona' => $persona,
                    ]);
                });
            }
        }

        return view('admin.reportar_equipo.index', compact('equipos', 'equipoSeleccionado', 'listado'));
    }

    public function create(Request $request)
    {
        $user = backpack_user();
        $equipos = EquipoCampania::with('ejercicioPolitico')
            ->where('coordinador_user_id', $user->id)
            ->get();

        $niveles = NivelAcademico::orderBy('nombre')->get();

        $personaData = session('reportar_persona');
        $cedula = session('reportar_cedula');
        $equipoId = session('reportar_equipo_id', $request->get('equipo_id'));

        return view('admin.reportar_equipo.create', compact('equipos', 'niveles', 'personaData', 'cedula', 'equipoId'));
    }

    public function buscar(Request $request)
    {
        $request->validate([
            'cedula_o_nit' => 'required|string|max:50',
            'equipo_id' => 'nullable|integer',
        ]);

        $cedula = trim($request->input('cedula_o_nit'));
        $persona = Persona::where('cedula_o_nit', $cedula)->first();

        if ($persona) {
            $data = $persona->only([
                'nombre_contratista',
                'cedula_o_nit',
                'celular',
                'genero',
                'nivel_academico_id',
                'tecnico_tecnologo_profesion',
                'especializacion',
                'maestria',
                'ejercicio_politico_origen_id',
            ]);
            session()->flash('reportar_persona', $data);
        } else {
            session()->flash('reportar_persona', null);
        }

        session()->flash('reportar_cedula', $cedula);
        session()->flash('reportar_equipo_id', $request->input('equipo_id'));

        return redirect()->route('reportar-equipo.create');
    }

    public function store(Request $request)
    {
        $personaExistente = Persona::where('cedula_o_nit', $request->input('cedula_o_nit'))->first();

        $rules = [
            'equipo_id' => 'required|exists:equipos_campania,id',
            'cedula_o_nit' => 'required|string|max:50',
            'foto' => 'nullable|image|max:2048',
            'documento_pdf' => 'nullable|mimes:pdf|max:5120',
            'nivel_academico_id' => 'nullable|exists:niveles_academicos,id',
            'tecnico_tecnologo_profesion' => 'nullable|string|max:255',
            'especializacion' => 'nullable|string|max:255',
            'maestria' => 'nullable|string|max:255',
        ];

        if (!$personaExistente) {
            $rules['nombre_contratista'] = 'required|string|max:255';
        }

        $data = $request->validate($rules);

        $user = backpack_user();
        $equipo = EquipoCampania::with('ejercicioPolitico')
            ->where('coordinador_user_id', $user->id)
            ->findOrFail($data['equipo_id']);

        if ($personaExistente) {
            $persona = $personaExistente;
        } else {
            $persona = new Persona();
            $persona->created_by_user_id = $user->id;
        }

        if ($request->filled('nombre_contratista')) {
            $persona->nombre_contratista = $request->input('nombre_contratista');
        }
        $persona->cedula_o_nit = $data['cedula_o_nit'];
        $persona->celular = $request->input('celular');
        $persona->genero = $request->input('genero');
        $persona->nivel_academico_id = $request->input('nivel_academico_id');
        $persona->tecnico_tecnologo_profesion = $request->input('tecnico_tecnologo_profesion');
        $persona->especializacion = $request->input('especializacion');
        $persona->maestria = $request->input('maestria');
        if (!$persona->ejercicio_politico_origen_id) {
            $persona->ejercicio_politico_origen_id = $equipo->ejercicio_politico_id;
        }

        if ($request->file('foto')) {
            $persona->foto = $request->file('foto');
        }
        if ($request->file('documento_pdf')) {
            $persona->documento_pdf = $request->file('documento_pdf');
        }

        $persona->save();

        // Asociar al equipo
        $equipo->personas()->syncWithoutDetaching([$persona->id]);

        // Asociar a campaña del equipo
        $equipo->ejercicioPolitico?->personas()->syncWithoutDetaching([$persona->id]);

        // Asociar referencia del coordinador sin borrar anteriores
        if ($user->referencia_id) {
            DB::table('persona_referencia')->insertOrIgnore([
                'persona_id' => $persona->id,
                'referencia_id' => $user->referencia_id,
            ]);
        }

        \Alert::success('Miembro reportado correctamente.')->flash();
        return redirect()->route('reportar-equipo.index', ['equipo_id' => $equipo->id]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'equipo_id' => 'required|exists:equipos_campania,id',
            'persona_id' => 'required|exists:personas,id',
        ]);

        $user = backpack_user();
        $equipo = EquipoCampania::with('ejercicioPolitico', 'coordinador')
            ->where('coordinador_user_id', $user->id)
            ->findOrFail($data['equipo_id']);

        $persona = Persona::findOrFail($data['persona_id']);

        $equipo->personas()->detach($persona->id);

        $refId = $equipo->coordinador?->referencia_id;
        if ($refId) {
            $stillLinked = DB::table('equipo_campania_persona')
                ->join('equipos_campania', 'equipos_campania.id', '=', 'equipo_campania_persona.equipo_campania_id')
                ->join('users', 'users.id', '=', 'equipos_campania.coordinador_user_id')
                ->where('equipo_campania_persona.persona_id', $persona->id)
                ->where('users.referencia_id', $refId)
                ->exists();

            if (!$stillLinked) {
                DB::table('persona_referencia')
                    ->where('persona_id', $persona->id)
                    ->where('referencia_id', $refId)
                    ->delete();
            }
        }

        \Alert::success('Miembro retirado del equipo.')->flash();
        return redirect()->route('reportar-equipo.index', ['equipo_id' => $equipo->id]);
    }

    public function deletePersona(Request $request)
    {
        $data = $request->validate([
            'equipo_id' => 'required|exists:equipos_campania,id',
            'persona_id' => 'required|exists:personas,id',
        ]);

        $user = backpack_user();
        $equipo = EquipoCampania::with('ejercicioPolitico', 'coordinador')
            ->where('coordinador_user_id', $user->id)
            ->findOrFail($data['equipo_id']);

        $persona = Persona::findOrFail($data['persona_id']);

        if ((int) $persona->created_by_user_id !== (int) $user->id) {
            \Alert::error('Solo puedes eliminar personas creadas por ti.')->flash();
            return redirect()->back();
        }

        $otrosEquipos = DB::table('equipo_campania_persona')
            ->where('persona_id', $persona->id)
            ->where('equipo_campania_id', '!=', $equipo->id)
            ->exists();

        if ($otrosEquipos) {
            \Alert::error('No se puede eliminar porque está asociado a otros equipos.')->flash();
            return redirect()->back();
        }

        // Desvincular del equipo actual
        $equipo->personas()->detach($persona->id);

        // Eliminar referencias asociadas al coordinador si ya no están en otros equipos
        $refId = $equipo->coordinador?->referencia_id;
        if ($refId) {
            DB::table('persona_referencia')
                ->where('persona_id', $persona->id)
                ->where('referencia_id', $refId)
                ->delete();
        }

        $persona->delete();

        \Alert::success('Persona eliminada correctamente.')->flash();
        return redirect()->route('reportar-equipo.index', ['equipo_id' => $equipo->id]);
    }
}
