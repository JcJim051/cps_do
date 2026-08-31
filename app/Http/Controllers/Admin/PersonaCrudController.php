<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PersonaRequest;
use App\Models\Persona;
use App\Models\Referencia;
use App\Models\NivelAcademico;
use App\Models\EstadoPersona; // Necesario para la nueva relación
use App\Models\EjercicioPolitico;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Exports\PeopleTemplateExport;
use App\Services\DatosAbiertosSecopService;
use App\Services\SecopConciliacionService;
use Illuminate\Http\Request;
use App\Imports\PeopleImport;
use Illuminate\Support\Facades\Cache;

class PersonaCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; edit as traitEdit; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function __construct(
        private DatosAbiertosSecopService $datosAbiertosSecopService,
        private SecopConciliacionService $secopConciliacionService,
    ) {
        parent::__construct();
    }

    public function setup(): void
    {
        CRUD::setModel(Persona::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/persona');
        CRUD::setEntityNameStrings('persona', 'personas');
         // Permitir borrar a roles autorizados (admin y diana).
         // Evita bloquear por hardcode cuando el rol diana tiene permiso funcional.
         if (!backpack_user()->hasAnyRole(['admin', 'diana'])) {
            CRUD::denyAccess('delete');
        }

        $user = backpack_user();
        if ($user && !$user->hasAnyRole(['admin', 'diana'])) {
            $this->crud->denyAccess(['list', 'show']);

            if ($user->referencia_id) {
                $this->crud->addClause('where', function ($query) use ($user) {
                    $query->whereHas('referencias', function ($q) use ($user) {
                        $q->where('referencia_id', $user->referencia_id);
                    })->orWhere('referencia_id', $user->referencia_id);
                });
            } else {
                $this->crud->denyAccess(['create', 'update']);
            }
        }
    }

    //------------------------------
    // LIST OPERATION
    //------------------------------
    protected function setupListOperation(): void
    {
        $this->crud->enableExportButtons();
        $user = backpack_user();


        $this->crud->addFilter([
            'name'  => 'nivel_academico_id',
            'type'  => 'select2',
            'label' => 'Nivel Académico'
        ], function () {
            return \App\Models\NivelAcademico::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'nivelAcademico', function ($q) use ($value) {
                $q->where('id', $value);
            });
        });
        
        // Estado Persona
        // $this->crud->addFilter([
        //     'name'  => 'estado_persona_id',
        //     'type'  => 'select2',
        //     'label' => 'Estado'
        // ], function () {
        //     return \App\Models\EstadoPersona::pluck('nombre', 'id')->toArray();
        // }, function ($value) {
        //     $this->crud->addClause('whereHas', 'estadoPersona', function ($q) use ($value) {
        //         $q->where('id', $value);
        //     });
        // });
        
        // Referencias (muchos a muchos)
        $this->crud->addFilter([
            'name'  => 'referencias',
            'type'  => 'select2',
            'label' => 'Referencia'
        ], function () {
            return \App\Models\Referencia::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'referencias', function ($q) use ($value) {
                $q->where('id', $value);
            });
        });

        $this->crud->addFilter([
            'name'  => 'prioridad_equipo',
            'type'  => 'select2',
            'label' => 'Priorización'
        ], function () {
            return [
                1 => '1',
                2 => '2',
                3 => '3',
            ];
        }, function ($value) {
            $this->crud->query->whereHas('equiposCampania', function ($q) use ($value) {
                $q->where('equipo_campania_persona.priorizacion', $value);
            });
        });


    
        // Profesión (text) - convertimos a select2 con valores únicos existentes
        $this->crud->addFilter([
            'name'  => 'profesion',
            'type'  => 'select2',
            'label' => 'Profesión'
        ], function () {
            return \App\Models\Persona::distinct()->pluck('tecnico_tecnologo_profesion', 'tecnico_tecnologo_profesion')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'tecnico_tecnologo_profesion', $value);
        });

        

        
        // Género
            $this->crud->addFilter([
                'name'  => 'genero',
                'type'  => 'select2',
                'label' => 'Género'
            ], function () {
                return [
                    'Femenino' => 'Femenino',
                    'Masculino' => 'Masculino',
                ];
            }, function ($value) {
                $this->crud->addClause('where', 'genero', $value);
            });

                // Caso
                $this->crud->addFilter([
                    'name'  => 'caso_id',
                    'type'  => 'select2',
                    'label' => 'Caso'
                ], function () {
                    return \App\Models\Caso::pluck('nombre', 'id')->toArray();
                }, function ($value) {
                    $this->crud->addClause('where', 'caso_id', $value);
                });
                // Secretaría
                // $this->crud->addFilter([
                //     'name'  => 'secretaria_id',
                //     'type'  => 'select2',
                //     'label' => 'Secretaría'
                // ], function () {
                //     return \App\Models\Secretaria::pluck('nombre', 'id')->toArray();
                // }, function ($value) {
                //     $this->crud->addClause('where', 'secretaria_id', $value);
                // });

                // // Gerencia
                // $this->crud->addFilter([
                //     'name'  => 'gerencia_id',
                //     'type'  => 'select2',
                //     'label' => 'Gerencia'
                // ], function () {
                //     return \App\Models\Gerencia::pluck('nombre', 'id')->toArray();
                // }, function ($value) {
                //     $this->crud->addClause('where', 'gerencia_id', $value);
                // });

        



        // Opcional: mantener los filtros en URL
        $this->crud->enablePersistentTable();
    
        // Nombre Contratista
        CRUD::addColumn([
            'name' => 'nombre_contratista',
            'label' => 'Nombre Contratista',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('nombre_contratista', 'like', '%'.$searchTerm.'%');
            },
        ]);
    
        // Cédula/NIT
        CRUD::addColumn([
            'name' => 'cedula_o_nit',
            'label' => 'Cédula o NIT',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('cedula_o_nit', 'like', '%'.$searchTerm.'%');
            },
        ]);

        // Celular
        CRUD::addColumn([
            'name' => 'celular',
            'label' => 'Celular',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('celular', 'like', '%'.$searchTerm.'%');
            },
        ]);
    
        
        // profesion
        CRUD::addColumn([
            'name' => 'tecnico_tecnologo_profesion',
            'label' => 'Profesion',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('tecnico_tecnologo_profesion', 'like', '%'.$searchTerm.'%');
            },
        ]);
            
        // Referencia (solo para admin o diana)
        if ($user && $user->roles->pluck('id')->intersect([1,2])->isNotEmpty()) {
            CRUD::addColumn([
                'name' => 'referencias', // ¡PLURAL!
                'label' => 'Referencia(s)',
                'type' => 'select_multiple', // Tipo para Muchos-a-Muchos
                'entity' => 'referencias',
                'model' => Referencia::class,
                'attribute' => 'nombre',
                'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhereHas('referencias', function($q) use ($searchTerm) {
                        $q->where('nombre', 'like', '%'.$searchTerm.'%');
                    });
                },
            ]);
        }
    
        // NUEVO CAMPO: Estado Persona (Relación - Reemplaza 'estado')
        // CRUD::addColumn([
        //     'label' => 'Estado',
        //     'type' => 'select',
        //     'name' => 'estado_persona_id',
        //     'entity' => 'estadoPersona', // Relación definida en el modelo
        //     'attribute' => 'nombre',
        //     'model' => EstadoPersona::class,
        //     'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
        //     'searchLogic' => function ($query, $column, $searchTerm) {
        //         $query->orWhereHas('estadoPersona', function($q) use ($searchTerm) {
        //             $q->where('nombre', 'like', '%'.$searchTerm.'%');
        //         });
        //     },
        // ]);

        CRUD::addColumn([
            'label' => 'Nivel Académico',
            'type' => 'select',
            'name' => 'nivel_academico_id',
            'entity' => 'nivelAcademico',
            'attribute' => 'nombre',
            'model' => NivelAcademico::class,
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('nivelAcademico', function($q) use ($searchTerm) {
                    $q->where('nombre', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);


        // Secretaría (usamos la relación)
        // CRUD::addColumn([
        //     'label' => 'Secretaría',
        //     'type' => 'select',
        //     'name' => 'secretaria_id',
        //     'entity' => 'secretaria',
        //     'attribute' => 'nombre',
        //     'model' => \App\Models\Secretaria::class,
        //     'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
        //     'searchLogic' => function ($query, $column, $searchTerm) {
        //         $query->orWhereHas('secretaria', function($q) use ($searchTerm) {
        //             $q->where('nombre', 'like', '%'.$searchTerm.'%');
        //         });
        //     },
        // ]); 

        CRUD::addColumn([
            'name' => 'maestria',
            'label' => 'Maestria',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('maestria', 'like', '%'.$searchTerm.'%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'especializacion',
            'label' => 'Especialización',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('especializacion', 'like', '%'.$searchTerm.'%');
            },
        ]);
        $this->crud->addButtonFromView('top', 'import', 'import_button', 'end'); // 'import_button' es el nombre de la vista que se crea abajo
        // ...
        // 🔧 Script para tooltips, una sola vez
        
                
                // ✅ Select para filtrar Nivel Académico
                $this->crud->addClause('where', function ($query) {
                    if (request()->has('filtro_nivel') && request('filtro_nivel') != '') {
                        $query->whereHas('nivelAcademico', function ($q) {
                            $q->where('nombre', request('filtro_nivel'));
                        });
                    }
                });

                // ✅ Select para filtrar Estado (USANDO LA NUEVA RELACIÓN)
                $this->crud->addClause('where', function ($query) {
                    if (request()->has('filtro_estado') && request('filtro_estado') != '') {
                        $query->whereHas('estadoPersona', function ($q) {
                            $q->where('nombre', request('filtro_estado'));
                        });
                    }
                });

                // ✅ Inyectamos los selects arriba de la tabla con custom_html
                // Se actualiza el filtro de estado para usar los datos del modelo EstadoPersona
            \Widget::add([
                'type'    => 'div',
                'class'   => 'row mb-2',
                'content' => '
                    <div class="col-md-4">
                        <select id="filtro_nivel" class="form-control">
                            <option value="">Filtrar por Nivel Académico</option>'
                            . collect(\App\Models\NivelAcademico::all())->map(function($n){
                                return '<option value="'.$n->nombre.'">'.$n->nombre.'</option>';
                            })->implode('') .
                        '</select>
                    </div>

                    <div class="col-md-4">
                        <select id="filtro_estado" class="form-control">
                            <option value="">Filtrar por Estado</option>'
                            . collect(\App\Models\EstadoPersona::all())->map(function($e){
                                return '<option value="'.$e->nombre.'">'.$e->nombre.'</option>';
                            })->implode('') .
                        '</select>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            let table = $(".dataTable").DataTable();

                            // Función genérica para manejar los filtros
                            function handleFilterChange(selectId, paramName) {
                                $("#" + selectId).on("change", function () {
                                    let val = this.value;
                                    let url = new URL(window.location.href);
                                    if (val) {
                                        url.searchParams.set(paramName, val);
                                    } else {
                                        url.searchParams.delete(paramName);
                                    }
                                    window.location.href = url.toString();
                                });
                                
                                // Mantener el valor seleccionado al recargar
                                let url = new URL(window.location.href);
                                let currentVal = url.searchParams.get(paramName);
                                if (currentVal) {
                                    $("#" + selectId).val(currentVal);
                                }
                            }

                            handleFilterChange("filtro_nivel", "filtro_nivel");
                            handleFilterChange("filtro_estado", "filtro_estado");
                        });
                    </script>
                ',
                'section' => 'before_list',
            ]);


       
    }
    
    
    //------------------------------
    // CREATE OPERATION
    //------------------------------
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(PersonaRequest::class);
    
        // --- FILA 1: Identificación ---
        CRUD::field('nombre_contratista')->label('Nombre Contratista')->wrapper(['class' => 'form-group col-md-4']);
        CRUD::field('cedula_o_nit')->label('Cédula o NIT')->wrapper(['class' => 'form-group col-md-4']);
        CRUD::field('celular')->label('Celular')->wrapper(['class' => 'form-group col-md-4']);
    
        // --- FILA 2: Género, Tipo y Estado (Nuevos campos) ---
        CRUD::field('genero')->label('Género')->type('select2_from_array')
            ->options(['Masculino' => 'Masculino', 'Femenino' => 'Femenino'])
            ->wrapper(['class' => 'form-group col-md-4']);
        
        // NUEVO CAMPO: Estado Persona (Reemplaza 'estado' y usa la relación)
        // CRUD::addField([
        //     'label'     => "Estado de la Persona",
        //     'type'      => 'select2',
        //     'name'      => 'estado_persona_id',
        //     'entity'    => 'estadoPersona',
        //     'attribute' => 'nombre',
        //     'model'     => EstadoPersona::class,
        //     'wrapper'   => ['class' => 'form-group col-md-4'],
        //     'allows_null' => true,
        //     'default' => EstadoPersona::first()->id ?? null,
        // ]);
    
        // --- FILA 3: Nivel Académico y Formación ---
        CRUD::addField([
            'label'     => "Nivel Académico",
            'type'      => 'select2',
            'name'      => 'nivel_academico_id',
            'entity'    => 'nivelAcademico',
            'attribute' => 'nombre',
            'model'     => NivelAcademico::class,
            'wrapper'   => ['class' => 'form-group col-md-4'],
            'allows_null' => true,
        ]);
    
        CRUD::field('tecnico_tecnologo_profesion')->label('Técnico/Tecnólogo/Profesión')->wrapper(['class' => 'form-group col-md-4']);
        CRUD::field('especializacion')->label('Especialización')->wrapper(['class' => 'form-group col-md-4']);
    
        // --- FILA 4: Maestría, Referencia y Caso ---
        CRUD::field('maestria')->label('Maestría')->wrapper(['class' => 'form-group col-md-4']);

        if (backpack_user()->hasAnyRole(['admin', 'diana'])) {
            CRUD::addField([
                'name' => 'referencias', // ¡PLURAL!
                'label' => 'Referencia(s)',
                'type' => 'select2_multiple', // ¡Campo de selección múltiple!
                'entity' => 'referencias',
                'model' => Referencia::class,
                'attribute' => 'nombre',
                'wrapper' => ['class' => 'form-group col-md-4'],
                'pivot' => true, // Importante para relaciones Many-to-Many
            ]);
        } else {
             CRUD::addField([
                 'name' => 'placeholder_ref',
                 'type' => 'custom_html',
                 'value' => '<label class="form-label">Referencia</label><div class="text-muted">Se asigna automáticamente según tu usuario.</div>',
                 'wrapper' => ['class' => 'form-group col-md-4'],
             ]);
        }
        if (backpack_user()->hasAnyRole(['admin', 'diana'])) {
            CRUD::field('referencia_2')->label('Referencia 2')->wrapper(['class' => 'form-group col-md-4']);
        } else {
            CRUD::addField(['name' => 'placeholder_ref2', 'type' => 'custom_html', 'value' => '', 'wrapper' => ['class' => 'form-group col-md-4']]);
        }
    
        CRUD::field('caso_id')->label('Caso Especial')->type('select2')
            ->entity('caso')
            ->attribute('nombre')
            ->model(\App\Models\Caso::class)
            ->wrapper(['class' => 'form-group col-md-4']);
    
        // --- FILA 5: Secretaría y Gerencia ---
            // // --- FILA 5: Secretaría ---
            // CRUD::addField([
            //     'name' => 'secretaria_id',
            //     'label' => 'Secretaría',
            //     'type' => 'select',
            //     'entity' => 'secretaria',
            //     'attribute' => 'nombre',
            //     'model' => \App\Models\Secretaria::class,
            //     'wrapper' => ['class' => 'form-group col-md-4'],
            //     'allows_null' => true,
            // ]);
        
            // CRUD::addField([
            //     'name' => 'gerencia_id',
            //     'label' => 'Gerencia',
            //     'type' => 'select2',
            //     'entity' => 'gerencia',
            //     'attribute' => 'nombre',
            //     'model' => \App\Models\Gerencia::class,
            //     'wrapper' => ['class' => 'form-group col-md-4'],
            //     'allows_null' => true,
            // ]);
        
        CRUD::addField([
            'name'  => 'filtrado_js',
            'type'  => 'custom_html',
            'value' => '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const secretaria = document.querySelector("[name=secretaria_id]");
                    const gerencia   = document.querySelector("[name=gerencia_id]");
    
                    function cargarGerencias(secretariaId) {
                        if (!secretariaId) return;
                        fetch("/admin/gerencias-por-secretaria/" + secretariaId)
                            .then(res => res.json())
                            .then(data => {
                                const currentGerenciaId = gerencia.value;
                                
                                gerencia.innerHTML = "";
                                
                                const defaultOption = document.createElement("option");
                                defaultOption.value = "";
                                defaultOption.text  = "- Seleccione una Gerencia -";
                                gerencia.appendChild(defaultOption);
                                
                                data.forEach(item => {
                                    const option = document.createElement("option");
                                    option.value = item.id;
                                    option.text  = item.text;
                                    
                                    if (item.id == currentGerenciaId) {
                                        option.selected = true;
                                    }
                                    
                                    gerencia.appendChild(option);
                                });
                            });
                    }
    
                    secretaria?.addEventListener("change", function() {
                        cargarGerencias(this.value);
                    });
    
                    if (secretaria?.value) {
                        cargarGerencias(secretaria.value);
                    }
                });
            </script>',
        ]);

        
        
        

        // --- FILA 6: Archivos ---
        CRUD::addField([
            'name' => 'foto',
            'label' => 'Foto (Imagen)',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
    
        CRUD::addField([
            'name' => 'documento_pdf',
            'label' => 'Documento (PDF)',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

    }
    
    //------------------------------
    // UPDATE OPERATION
    //------------------------------
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
        
    }

    public function edit($id)
    {
        $this->authorizePersonaAccess($id);
        return $this->traitEdit($id);
    }

    public function update()
    {
        $id = request()->route('id');
        $this->authorizePersonaAccess($id);
        return $this->traitUpdate();
    }

    protected function authorizePersonaAccess($id): void
    {
        $user = backpack_user();
        if (!$user || $user->hasAnyRole(['admin', 'diana'])) {
            return;
        }

        $persona = Persona::with('referencias')->findOrFail($id);
        $referenciaId = $user->referencia_id;

        if (!$referenciaId) {
            abort(403);
        }

        $hasReferencia = $persona->referencia_id === $referenciaId
            || $persona->referencias->contains('id', $referenciaId);

        if (!$hasReferencia) {
            abort(403);
        }
    }

    //------------------------------
    // SHOW OPERATION
    //------------------------------
        protected function setupShowOperation(): void
        {
            $this->crud->set('show.setFromDb', false);
            $this->crud->set('show.contentClass', 'container-fluid');
            // Información principal + foto + pdf
            $this->crud->addColumn([
                'name'     => 'datos_persona',
                'label'    => 'Información de la Persona',
                'type'     => 'closure',
                'function' => function ($entry) {
                    $entry->loadMissing([
                        'referencias', 'nivelAcademico', 'caso',
                        'seguimientos.estadoContrato', 'seguimientos.secretaria', 'seguimientos.vinculoSecop',
                    ]);
                    return view('admin.persona.profile_overview', compact('entry'))->render();
                },
                'escaped' => false,
            ]);

            $this->crud->addColumn([
                'name'     => 'trazabilidad_campanias',
                'label'    => 'Trazabilidad en Campañas y Equipos',
                'type'     => 'closure',
                'function' => function ($entry) {
                    $equipos = $entry->equiposCampania()->with('ejercicioPolitico')->get();
                    $equiposActivos = $equipos->keyBy('id');

                    $historial = \DB::table('equipo_campania_persona_historial')
                        ->join('equipos_campania', 'equipos_campania.id', '=', 'equipo_campania_persona_historial.equipo_campania_id')
                        ->leftJoin('ejercicios_politicos', 'ejercicios_politicos.id', '=', 'equipos_campania.ejercicio_politico_id')
                        ->where('equipo_campania_persona_historial.persona_id', $entry->id)
                        ->select([
                            'equipos_campania.id as equipo_id',
                            'equipos_campania.nombre as equipo_nombre',
                            'ejercicios_politicos.id as campania_id',
                            'ejercicios_politicos.nombre as campania_nombre',
                        ])
                        ->distinct()
                        ->get();

                    if ($equipos->isEmpty() && $historial->isEmpty()) {
                        return '<p class="text-muted">Sin participación registrada.</p>';
                    }

                    $rows = collect();
                    foreach ($equipos as $equipo) {
                        $rows->push([
                            'equipo_id' => $equipo->id,
                            'campania_id' => $equipo->ejercicioPolitico?->id,
                            'campania' => $equipo->ejercicioPolitico?->nombre ?? '-',
                            'equipo' => $equipo->nombre,
                            'priorizacion' => $equipo->pivot->priorizacion,
                            'estado' => 'Activo',
                        ]);
                    }
                    foreach ($historial as $row) {
                        if ($equiposActivos->has($row->equipo_id)) {
                            continue;
                        }
                        $rows->push([
                            'equipo_id' => $row->equipo_id,
                            'campania_id' => $row->campania_id,
                            'campania' => $row->campania_nombre ?? '-',
                            'equipo' => $row->equipo_nombre ?? '-',
                            'priorizacion' => null,
                            'estado' => 'Retirado',
                        ]);
                    }

                    $equiposUnicos = $rows->pluck('equipo_id')->filter()->unique();
                    $esNuevoGlobal = $equiposUnicos->count() <= 1;

                    $tieneRetirados = $rows->contains(function ($row) {
                        return $row['estado'] === 'Retirado';
                    });

                    $collapseId = 'trazabilidad-'.$entry->id;
                    $html = '<div class="persona-section" data-section="trazabilidad">';
                    $html .= '<div class="card mt-3 persona-tone-card">';
                    $html .= '<div class="card-header persona-tone-header d-flex justify-content-between align-items-center">';
                    $html .= '<span>Campañas y Equipos</span>';
                    $html .= '<button class="btn btn-sm btn-outline-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-expanded="false">Ver</button>';
                    $html .= '</div>';
                    $html .= '<div id="'.$collapseId.'" class="collapse">';
                    $html .= '<div class="card-body p-0">';
                    $html .= '<div class="table-responsive"><table class="table mb-0 table-striped align-middle">';
                    $html .= '<thead class="table-light"><tr><th>Campaña</th><th>Equipo</th><th>Priorización</th>';
                    if ($tieneRetirados) {
                        $html .= '<th>Estado</th>';
                    }
                    $html .= '<th>Nuevo</th></tr></thead><tbody>';

                    foreach ($rows as $row) {
                        $priorizacion = $row['priorizacion'];
                        $label = $priorizacion !== null ? (string) $priorizacion : '-';
                        $color = $priorizacion !== null ? 'text-body fw-semibold' : 'text-muted';
                        $estadoClass = $row['estado'] === 'Activo' ? 'text-success fw-semibold' : 'text-muted';
                        $nuevoLabel = $esNuevoGlobal ? 'Sí' : 'No';
                        $nuevoClass = $esNuevoGlobal ? 'text-success fw-semibold' : 'text-muted';
                        $html .= '<tr>';
                        $html .= '<td>'.e($row['campania']).'</td>';
                        $html .= '<td>'.e($row['equipo']).'</td>';
                        $html .= '<td><span class="'.$color.'">'.$label.'</span></td>';
                        if ($tieneRetirados) {
                            $html .= '<td><span class="'.$estadoClass.'">'.$row['estado'].'</span></td>';
                        }
                        $html .= '<td><span class="'.$nuevoClass.'">'.$nuevoLabel.'</span></td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody></table></div></div></div></div>';
                    $html .= '</div>';
                    return $html;
                },
                'escaped' => false,
            ]);

            // Tabla de seguimientos (SE MANTIENE TAL CUAL)
            $this->crud->addColumn([
                'name'     => 'seguimientos',
                'label'    => 'Seguimientos Cto',
                'type'     => 'closure',
                'function' => function ($entry) {
                    if ($entry->seguimientos->isEmpty()) {
                        return '<p class="text-muted">Sin seguimientos registrados.</p>';
                    }
        
                    $collapseId = 'seguimientos-cto-'.$entry->id;
                    $html = '<div class="persona-section" data-section="seguimientos_cto">';
                    $html .= '<div class="mt-4 card persona-tone-card">
                                <div class="card-header persona-tone-header persona-tone-header--success d-flex justify-content-between align-items-center">
                                    <span>Seguimientos</span>
                                    <button class="btn btn-sm btn-outline-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-expanded="false">Ver</button>
                                </div>
                                <div id="'.$collapseId.'" class="collapse">
                                <div class="p-0 card-body">
                                    <div class="table-responsive">
                                        <table class="table mb-0 align-middle table-striped">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Tipo</th>
                                                    <th>Secretaría</th>
                                                    <th>Año/Fecha</th> <!-- Encabezado actualizado para claridad -->
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
        
                    foreach ($entry->seguimientos as $i => $seg) {
                        
                        // 1. Lógica para el ESTADO (CORREGIDA)
                        $estado = '-';
                        if ($seg->tipo === 'contrato') {
                            // Si es contrato, buscamos el nombre a través de la relación estadoContrato
                            $estado = $seg->estadoContrato?->nombre ?? '-'; 
                        } elseif ($seg->tipo === 'entrevista') {
                            // SI ES ENTREVISTA, accedemos a la propiedad 'nombre' del objeto/JSON que está en $seg->estado
                            $estado = $seg->estado?->nombre ?? $seg->estado['nombre'] ?? '-';
                        } else {
                            // Para otros tipos genéricos, usamos el valor simple (si es un string)
                            $estado = $seg->estado ?? '-'; 
                        }
        
                        // 2. Lógica para Secretaría/Dependencia
                        $secretariaNombre = $seg->secretaria?->nombre ?? ($seg->dependencia ?? '-');
                        
                        // 3. Lógica para Año o Fecha de Entrevista
                        $anioOFecha = '-';
                        if ($seg->tipo === 'contrato') {
                            $anioOFecha = $seg->anio ?? '-';
                        } elseif ($seg->tipo === 'entrevista') {
                            $anioOFecha = $seg->fecha_entrevista ?? '-'; 
                        }
        
                        $html .= '<tr>
                                    <td>'.($i+1).'</td>
                                    <td>'.ucfirst($seg->tipo).'</td>
                                    <td>'.$secretariaNombre.'</td>
                                    <td>'.$anioOFecha.'</td> <!-- VALOR CON LA LÓGICA CONDICIONAL -->
                                    <td>'.$estado.'</td>
                                    <td>
                                        <a href="'.url('admin/seguimiento/'.$seg->id.'/show').'" class="btn btn-sm btn-outline-primary">Ver</a>
                                    </td>
                                </tr>';
                    }
        
                    $html .= '           </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            </div>';
                    $html .= '</div>';
        
                    return $html;
                },
                'escaped' => false,
            ]);

            $this->crud->addColumn([
                'name' => 'conciliacion_secop',
                'label' => 'Conciliación SECOP',
                'type' => 'closure',
                'function' => function ($entry) {
                    try {
                        $resultado = $this->secopConciliacionService->conciliarPersona($entry);
                        return view('admin.persona.secop_conciliacion', compact('resultado'))->render();
                    } catch (\Throwable $e) {
                        \Log::warning('No se pudo simular la conciliación SECOP de la persona '.$entry->id.': '.$e->getMessage());
                        return '<div class="alert alert-warning">No fue posible consultar la conciliación SECOP. Intenta nuevamente en unos minutos.</div>';
                    }
                },
                'escaped' => false,
            ]);

            $this->crud->addColumn([
                'name'     => 'seguimientos_nom',
                'label'    => 'Seguimientos Nom',
                'type'     => 'closure',
                'function' => function ($entry) {
                    if ($entry->seguimientosNom->isEmpty()) {
                        return '<p class="text-muted">Sin seguimientos Nom registrados.</p>';
                    }

                    $collapseId = 'seguimientos-nom-'.$entry->id;
                    $html = '<div class="persona-section" data-section="seguimientos_nom">';
                    $html .= '<div class="mt-4 card persona-tone-card">
                                <div class="card-header persona-tone-header persona-tone-header--warning d-flex justify-content-between align-items-center">
                                    <span>Seguimientos Nom</span>
                                    <button class="btn btn-sm btn-outline-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-expanded="false">Ver</button>
                                </div>
                                <div id="'.$collapseId.'" class="collapse">
                                <div class="p-0 card-body">
                                    <div class="table-responsive">
                                        <table class="table mb-0 align-middle table-striped">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Dependencia</th>
                                                    <th>Cargo</th>
                                                    <th>Tipo Vinculación</th>
                                                    <th>Fecha Ingreso</th>
                                                    <th>Salario</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>';

                    foreach ($entry->seguimientosNom as $i => $seg) {
                        $salario = $seg->salario !== null
                            ? '$ ' . number_format((float) $seg->salario, 0, ',', '.')
                            : '-';
                        $html .= '<tr>
                                    <td>'.($i+1).'</td>
                                    <td>'.e($seg->secretaria?->nombre ?? '-').'</td>
                                    <td>'.e($seg->cargo?->nombre ?? '-').'</td>
                                    <td>'.e($seg->tipoVinculacion?->nombre ?? '-').'</td>
                                    <td>'.e($seg->fecha_ingreso ?? '-').'</td>
                                    <td>'.$salario.'</td>
                                    <td>
                                        <a href="'.url('admin/seguimiento-nom/'.$seg->id.'/show').'" class="btn btn-sm btn-outline-primary">Ver</a>
                                    </td>
                                </tr>';
                    }

                    $html .= '           </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            </div>';
                    $html .= '</div>';

                    return $html;
                },
                'escaped' => false,
            ]);

            $this->crud->addColumn([
                'name'     => 'datos_abiertos',
                'label'    => 'Datos abiertos',
                'type'     => 'closure',
                'function' => function ($entry) {
                    $cedula = trim((string) ($entry->cedula_o_nit ?? ''));
                    if ($cedula === '') {
                        return '<p class="text-muted">Sin documento para consultar Datos Abiertos.</p>';
                    }

                    $cacheKey = 'datos_abiertos_contratos_' . $cedula;
                    try {
                        $result = Cache::remember($cacheKey, 600, function () use ($cedula) {
                            return $this->datosAbiertosSecopService->consultarPorDocumento($cedula, '2024-01-01');
                        });
                    } catch (\Throwable $e) {
                        return '<p class="text-muted">No se pudo consultar Datos Abiertos.</p>';
                    }

                    if (empty($result)) {
                        return '<p class="text-muted">Sin contratos desde 2024.</p>';
                    }

                    $safe = function ($value) {
                        if (is_array($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                        if ($value === null || $value === '') {
                            return '-';
                        }
                        return e((string) $value);
                    };

                    $collapseId = 'datos-abiertos-'.$entry->id;
                    $html = '<div class="persona-section" data-section="datos_abiertos">';
                    $html .= '<div class="card mt-3 persona-tone-card">';
                    $html .= '<div class="card-header persona-tone-header persona-tone-header--purple d-flex justify-content-between align-items-center">';
                    $html .= '<span>Datos abiertos</span>';
                    $html .= '<button class="btn btn-sm btn-outline-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-expanded="false">Ver</button>';
                    $html .= '</div>';
                    $html .= '<div id="'.$collapseId.'" class="collapse">';
                    $html .= '<div class="card-body p-0">';
                    $html .= '<div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap gap-3 small text-muted">'
                        . '<span><span style="display:inline-block;width:12px;height:12px;border-left:3px solid #0d6efd;background:#fff;margin-right:6px;vertical-align:-1px;"></span>SECOP I</span>'
                        . '<span><span style="display:inline-block;width:12px;height:12px;border-left:3px solid transparent;background:#fff;margin-right:6px;vertical-align:-1px;"></span>SECOP II</span>'
                        . '</div>';
                    $html .= '<div class="table-responsive"><table class="table mb-0 table-striped align-middle">';
                    $html .= '<thead class="table-light"><tr>'
                        . '<th>Entidad</th>'
                        . '<th>Estado</th>'
                        . '<th>Tipo</th>'
                        . '<th>Fecha firma</th>'
                        . '<th>Inicio</th>'
                        . '<th>Fin</th>'
                        . '<th>Valor</th>'
                        . '<th>URL proceso</th>'
                        . '</tr></thead><tbody>';

                    foreach ($result as $row) {
                        $url = $row['url'] ?? '';
                        $urlHtml = $url ? '<a href="'.e($url).'" target="_blank">Ver</a>' : '-';
                        $valor = $row['valor_total_con_adiciones'] ?? $row['valor_contrato'] ?? null;
                        if (is_numeric($valor)) {
                            $valor = '$ ' . number_format((float) $valor, 0, ',', '.');
                        }
                        $fechaFirma = $row['fecha_firma'] ?? null;
                        $fechaInicio = $row['fecha_inicio'] ?? null;
                        $fechaFin = $row['fecha_fin'] ?? null;
                        $nombreEntidad = $row['nombre_entidad'] ?? null;
                        $esMeta = is_string($nombreEntidad)
                            && mb_strtoupper(trim($nombreEntidad)) === 'DEPARTAMENTO DEL META';
                        $sourceClass = ($row['fuente_codigo'] ?? '') === 'secop1' ? 'secop1-row' : '';
                        $rowClass = trim(($esMeta ? '' : 'table-warning').' '.$sourceClass);
                        $html .= '<tr class="'.$rowClass.'">';
                        $html .= '<td>'.$safe($nombreEntidad).'</td>';
                        $html .= '<td>'.$safe($row['estado'] ?? null).'</td>';
                        $html .= '<td>'.$safe($row['tipo'] ?? null).'</td>';
                        $html .= '<td>'.$safe($fechaFirma).'</td>';
                        $html .= '<td>'.$safe($fechaInicio).'</td>';
                        $html .= '<td>'.$safe($fechaFin).'</td>';
                        $html .= '<td>'.$safe($valor).'</td>';
                        $html .= '<td>'.$urlHtml.'</td>';
                        $html .= '</tr>';
                    }

                    $html .= '</tbody></table></div></div></div></div>';
                    $html .= '<style>
                        .secop1-row td:first-child{border-left:3px solid #0d6efd;}
                    </style>';
                    $html .= '</div>';
                    return $html;
                },
                'escaped' => false,
            ]);

        }

        public function importForm()
        {
            $this->crud->hasAccessOrFail('create');
            $this->data['crud'] = $this->crud;
            $this->data['title'] = 'Importar Personas';

            // Usa una vista en resources/views/admin/person/import.blade.php
            return view('admin.person.import', $this->data); 
        }

        public function import(Request $request)
        {
            $this->crud->hasAccessOrFail('create');
        
            $request->validate([
                'file' => 'required|file|mimes:csv,xlsx,xls',
            ]);
        
            // Usamos una instancia del importador para leer sus errores luego
            $import = new PeopleImport();
        
            try {
                $import->import($request->file('file'));
            } catch (\Throwable $e) {
                $rawMessage = $e->getMessage();
            
                // Detectar errores comunes para simplificarlos ✅
                if (str_contains($rawMessage, 'foreign key constraint fails')) {
                    $cleanMessage = 'Referencia inválida: Verifica secretaria_id, gerencia_id o referencia_id';
                } elseif (str_contains($rawMessage, 'Duplicate entry')) {
                    $cleanMessage = 'Ya existe una persona con esta cédula';
                } else {
                    $cleanMessage = substr($rawMessage, 0, 100) . '...'; // Limitar a 100 caracteres
                }
            
                $this->logicFailures[] = [
                    'row' => $rowNumber ?? 'N/A',
                    'cedula' => $row['cedula_o_nit'] ?? 'N/A',
                    'errors' => [$cleanMessage],
                ];
            
                Log::error("Error de BD/Asignación en Cédula " . ($row['cedula_o_nit'] ?? 'N/A') . ". Mensaje resumido: " . $cleanMessage);
            }
        
            // ✅ Si hay errores de lógica, los retornamos a la vista para listarlos
            if (!empty($import->logicFailures)) {
                return view('admin.person.import', [
                    'crud' => $this->crud,
                    'title' => 'Importar Personas',
                    'importFailures' => $import->logicFailures // <-- PASAMOS LOS ERRORES A LA VISTA
                ]);
            }
        
            \Alert::success('Registros importados exitosamente.')->flash();
            return redirect($this->crud->route);
        }
        
        public function downloadTemplate()
        {
            return \Excel::download(new PeopleTemplateExport, 'plantilla_personas.xlsx');
        }
        }
