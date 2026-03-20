<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\EquipoCampaniaRequest;
use App\Models\EquipoCampania;
use App\Models\EjercicioPolitico;
use App\Models\Persona;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class EquipoCampaniaCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(EquipoCampania::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/equipo-campania');
        CRUD::setEntityNameStrings('equipo de campaña', 'equipos de campaña');

        $user = backpack_user();
        if ($user && $user->hasAnyRole(['coordinador', 'coordinador_comite'])) {
            $this->crud->denyAccess(['list', 'show', 'create', 'update', 'delete']);
        }
        if ($user && !$user->hasAnyRole(['admin', 'diana'])) {
            $this->crud->addClause('where', 'coordinador_user_id', $user->id);
            $this->crud->denyAccess(['create', 'delete']);
        }
    }

    protected function setupListOperation(): void
    {
        $this->crud->addFilter([
            'name' => 'ejercicio_politico_id',
            'type' => 'select2',
            'label' => 'Campaña',
        ], function () {
            return EjercicioPolitico::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'ejercicio_politico_id', $value);
        });

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
        ]);

        CRUD::addColumn([
            'name' => 'nombre',
            'label' => 'Equipo',
        ]);

        CRUD::addColumn([
            'name' => 'ejercicio_politico_id',
            'label' => 'Campaña',
            'type' => 'select',
            'entity' => 'ejercicioPolitico',
            'model' => EjercicioPolitico::class,
            'attribute' => 'nombre',
        ]);

        CRUD::addColumn([
            'name' => 'coordinador_user_id',
            'label' => 'Coordinador',
            'type' => 'select',
            'entity' => 'coordinador',
            'model' => User::class,
            'attribute' => 'name',
        ]);

        CRUD::addColumn([
            'name' => 'personas',
            'label' => 'Integrantes',
            'type' => 'select_multiple',
            'entity' => 'personas',
            'model' => Persona::class,
            'attribute' => 'nombre_contratista',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(EquipoCampaniaRequest::class);

        $user = backpack_user();
        if ($user && $user->hasAnyRole(['admin', 'diana'])) {
            CRUD::addField([
                'name' => 'ejercicio_politico_id',
                'label' => 'Campaña',
                'type' => 'select2',
                'entity' => 'ejercicioPolitico',
                'model' => EjercicioPolitico::class,
                'attribute' => 'nombre',
            ]);

            CRUD::addField([
                'name' => 'nombre',
                'label' => 'Nombre del equipo',
                'type' => 'text',
            ]);

            CRUD::addField([
                'name' => 'coordinador_user_id',
                'label' => 'Coordinador',
                'type' => 'select2',
                'entity' => 'coordinador',
                'model' => User::class,
                'attribute' => 'name',
                'options' => function ($query) {
                    return $query->orderBy('name', 'asc')->get();
                },
            ]);
        }

        CRUD::addField([
            'name' => 'personas',
            'label' => 'Integrantes',
            'type' => 'select2_multiple',
            'entity' => 'personas',
            'model' => Persona::class,
            'attribute' => 'nombre_contratista',
            'pivot' => true,
            'options' => function ($query) use ($user) {
                if ($user && !$user->hasAnyRole(['admin', 'diana']) && $user->referencia_id) {
                    return $query->whereHas('referencias', function ($q) use ($user) {
                        $q->where('referencia_id', $user->referencia_id);
                    })->orderBy('nombre_contratista', 'asc')->get();
                }

                return $query->orderBy('nombre_contratista', 'asc')->get();
            },
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $user = backpack_user();
        $mostrarReferenciasSecretas = $user && $user->hasAnyRole(['admin', 'diana']);
        $referenciaActual = $user?->referencia?->nombre;

        $this->crud->set('show.setFromDb', false);

        CRUD::addColumn([
            'name' => 'nombre',
            'label' => 'Equipo',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'ejercicio_politico_id',
            'label' => 'Campaña',
            'type' => 'select',
            'entity' => 'ejercicioPolitico',
            'model' => EjercicioPolitico::class,
            'attribute' => 'nombre',
        ]);

        CRUD::addColumn([
            'name' => 'coordinador_user_id',
            'label' => 'Coordinador',
            'type' => 'select',
            'entity' => 'coordinador',
            'model' => User::class,
            'attribute' => 'name',
        ]);

        $this->crud->addColumn([
            'name'     => 'integrantes_detalle',
            'label'    => 'Integrantes',
            'type'     => 'closure',
            'function' => function ($entry) use ($mostrarReferenciasSecretas, $referenciaActual) {
                $personas = $entry->personas()->with('referencias')->get();

                if ($personas->isEmpty()) {
                    return '<p class="text-muted">Sin integrantes.</p>';
                }

                $tableId = 'equipos-personas-' . $entry->id;
                $html = '<div class="table-responsive"><table id="'.$tableId.'" class="table table-striped align-middle mb-0">';
                $html .= '<thead><tr><th>Nombre</th><th>Cédula/NIT</th>';
                if ($mostrarReferenciasSecretas) {
                    $html .= '<th>Referencias</th>';
                } else {
                    $html .= '<th>Referencia actual</th>';
                }
                $html .= '<th>Acciones</th></tr></thead><tbody>';

                foreach ($personas as $persona) {
                    $html .= '<tr>';
                    $html .= '<td>'.$persona->nombre_contratista.'</td>';
                    $html .= '<td>'.$persona->cedula_o_nit.'</td>';
                    if ($mostrarReferenciasSecretas) {
                        $refs = $persona->referencias->pluck('nombre')->implode(', ');
                        $html .= '<td>'.($refs ?: '-').'</td>';
                    } else {
                        $html .= '<td>'.($referenciaActual ?: '-').'</td>';
                    }
                    $html .= '<td><a class="btn btn-sm btn-outline-primary" href="'.backpack_url('persona/'.$persona->id.'/edit').'">Editar</a></td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table></div>';
                $html .= '<script>
                    document.addEventListener("DOMContentLoaded", function () {
                        if (window.jQuery && window.jQuery.fn.dataTable) {
                            if (!jQuery.fn.dataTable.isDataTable("#'.$tableId.'")) {
                                jQuery("#'.$tableId.'").DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50, 100],
                                    order: [],
                                    language: {
                                        search: "Buscar:",
                                        lengthMenu: "Mostrar _MENU_",
                                        info: "Mostrando _START_ a _END_ de _TOTAL_",
                                        infoEmpty: "Mostrando 0 a 0 de 0",
                                        zeroRecords: "Sin resultados",
                                        paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
                                    }
                                });
                            }
                        }
                    });
                </script>';
                return $html;
            },
            'escaped' => false,
        ]);
    }

    public function store()
    {
        $response = $this->traitStore();
        $this->syncPersonasToCampaign();
        return $response;
    }

    public function update()
    {
        $removed = [];
        $entry = $this->crud->getCurrentEntry();
        if ($entry) {
            $currentIds = $entry->personas()->pluck('personas.id')->all();
            $requestedIds = request()->input('personas', []);
            $requestedIds = is_array($requestedIds) ? $requestedIds : [];
            $removed = array_diff($currentIds, $requestedIds);
        }
        $user = backpack_user();
        if ($user && !$user->hasAnyRole(['admin', 'diana'])) {
            if (!empty($removed) && $entry) {
                $noPermitidos = \App\Models\Persona::whereIn('id', $removed)
                    ->where('ejercicio_politico_origen_id', '!=', $entry->ejercicio_politico_id)
                    ->count();

                if ($noPermitidos > 0) {
                    \Alert::error('Solo puedes retirar miembros nuevos (creados en esta campaña).')->flash();
                    return redirect()->back()->withInput();
                }
            }
        }

        $response = $this->traitUpdate();
        $this->syncPersonasToCampaign();
        if (!empty($removed)) {
            $this->removeReferenciaForRemoved($removed);
        }
        return $response;
    }

    protected function removeReferenciaForRemoved(array $removedPersonaIds): void
    {
        $entry = $this->crud->getCurrentEntry();
        if (!$entry || empty($removedPersonaIds)) {
            return;
        }

        $refId = $entry->coordinador?->referencia_id;
        if (!$refId) {
            return;
        }

        foreach ($removedPersonaIds as $personaId) {
            $stillLinked = \DB::table('equipo_campania_persona')
                ->join('equipos_campania', 'equipos_campania.id', '=', 'equipo_campania_persona.equipo_campania_id')
                ->join('users', 'users.id', '=', 'equipos_campania.coordinador_user_id')
                ->where('equipo_campania_persona.persona_id', $personaId)
                ->where('users.referencia_id', $refId)
                ->exists();

            if (!$stillLinked) {
                \DB::table('persona_referencia')
                    ->where('persona_id', $personaId)
                    ->where('referencia_id', $refId)
                    ->delete();
            }
        }
    }

    protected function syncPersonasToCampaign(): void
    {
        $entry = $this->crud->getCurrentEntry();
        if (!$entry) {
            return;
        }

        $personaIds = $entry->personas()->pluck('personas.id')->all();
        if (!empty($personaIds)) {
            $entry->ejercicioPolitico
                ?->personas()
                ->syncWithoutDetaching($personaIds);
        }

        $user = backpack_user();
        if ($user && !$user->hasAnyRole(['admin', 'diana']) && $user->referencia_id && !empty($personaIds)) {
            $rows = [];
            foreach ($personaIds as $personaId) {
                $rows[] = [
                    'persona_id' => $personaId,
                    'referencia_id' => $user->referencia_id,
                ];
            }
            \DB::table('persona_referencia')->insertOrIgnore($rows);
        }
    }
}
