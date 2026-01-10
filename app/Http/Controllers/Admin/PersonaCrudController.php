<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PersonaRequest;
use App\Models\Persona;
use App\Models\Referencia;
use App\Models\NivelAcademico;
use App\Models\EstadoPersona; // Necesario para la nueva relación
use App\Models\Tipo; // Necesario para la nueva relación
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Exports\PeopleTemplateExport;
use Illuminate\Http\Request;
use App\Imports\PeopleImport;

class PersonaCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Persona::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/persona');
        CRUD::setEntityNameStrings('persona', 'personas');
         // 🔒 Solo el Admin (id 1) puede borrar
         if (!backpack_user()->hasRole('admin')) {
            CRUD::denyAccess('delete');
            // Si NO es Admin o Diana, le quitamos permisos de crear/editar
        if (!backpack_user()->hasAnyRole(['admin', 'diana'])) {
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
        $this->crud->addFilter([
            'name'  => 'estado_persona_id',
            'type'  => 'select2',
            'label' => 'Estado'
        ], function () {
            return \App\Models\EstadoPersona::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'estadoPersona', function ($q) use ($value) {
                $q->where('id', $value);
            });
        });
        
        // Tipo
        $this->crud->addFilter([
            'name'  => 'tipo_id',
            'type'  => 'select2',
            'label' => 'Tipo'
        ], function () {
            return \App\Models\Tipo::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'tipo', function ($q) use ($value) {
                $q->where('id', $value);
            });
        });
        
      
        
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
                $this->crud->addFilter([
                    'name'  => 'secretaria_id',
                    'type'  => 'select2',
                    'label' => 'Secretaría'
                ], function () {
                    return \App\Models\Secretaria::pluck('nombre', 'id')->toArray();
                }, function ($value) {
                    $this->crud->addClause('where', 'secretaria_id', $value);
                });

                // Gerencia
                $this->crud->addFilter([
                    'name'  => 'gerencia_id',
                    'type'  => 'select2',
                    'label' => 'Gerencia'
                ], function () {
                    return \App\Models\Gerencia::pluck('nombre', 'id')->toArray();
                }, function ($value) {
                    $this->crud->addClause('where', 'gerencia_id', $value);
                });

        



        // Opcional: mantener los filtros en URL
        $this->crud->enablePersistentTable();
    
        // Nombre Contratista
        CRUD::addColumn([
            'name' => 'nombre_contratista',
            'label' => 'Nombre Contratista',
            'type' => 'closure',
            'function' => function ($entry) {
                $nombre = e($entry->nombre_contratista);
                if ($entry->no_tocar) {
                    return '<strong style="color:#6610f2;" data-bs-toggle="tooltip" title="No Tocar">'
                         . $nombre . '</strong>';
                }
                return $nombre;
            },
            'escaped' => false,
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
        CRUD::addColumn([
            'label' => 'Estado',
            'type' => 'select',
            'name' => 'estado_persona_id',
            'entity' => 'estadoPersona', // Relación definida en el modelo
            'attribute' => 'nombre',
            'model' => EstadoPersona::class,
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('estadoPersona', function($q) use ($searchTerm) {
                    $q->where('nombre', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        // NUEVO CAMPO: Tipo (Relación)
        CRUD::addColumn([
            'label' => 'Tipo',
            'type' => 'select',
            'name' => 'tipo_id',
            'entity' => 'tipo', // Relación definida en el modelo
            'attribute' => 'nombre',
            'model' => Tipo::class,
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('tipo', function($q) use ($searchTerm) {
                    $q->where('nombre', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);
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
        // No Tocar (badge visual)
        CRUD::addColumn([
            'name' => 'no_tocar',
            'label' => 'No Tocar',
            'type' => 'closure',
            'function' => function ($entry) {
                return $entry->no_tocar
                    ? '<span class="badge bg-danger">NO TOCAR</span>'
                    : '<span class="badge bg-secondary">-</span>';
            },
            'escaped' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                if (stripos('NO TOCAR', $searchTerm) !== false) {
                    $query->orWhere('no_tocar', 1);
                }
            },
        ]);

        // Secretaría (usamos la relación)
        CRUD::addColumn([
            'label' => 'Secretaría',
            'type' => 'select',
            'name' => 'secretaria_id',
            'entity' => 'secretaria',
            'attribute' => 'nombre',
            'model' => \App\Models\Secretaria::class,
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('secretaria', function($q) use ($searchTerm) {
                    $q->where('nombre', 'like', '%'.$searchTerm.'%');
                });
            },
        ]); 

        CRUD::addColumn([
            'name' => 'maestria',
            'label' => 'Maestria',
            'type' => 'text',
            'wrapper' => ['style' => 'font-size:13px; white-space:normal;'],
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('maestria', 'like', '%'.$searchTerm.'%');
            },
        ]);
        $this->crud->addButtonFromView('top', 'import', 'import_button', 'end'); // 'import_button' es el nombre de la vista que se crea abajo
        // ...
        // 🔧 Script para tooltips, una sola vez
        $this->crud->addColumn([
            'name' => '_init_tooltips_once',
            'label' => '',
            'type' => 'closure',
            'function' => function ($entry) {
                static $printed = false;
                if ($printed) return '';
                $printed = true;
                return <<<'EOT'
                <script>
                (function(){
                    function initTooltips(){
                        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
                            if (!el._bs_tooltip) {
                                new bootstrap.Tooltip(el);
                                el._bs_tooltip = true;
                            }
                        });
                    }
                    document.addEventListener('DOMContentLoaded', initTooltips);
                    if (window.jQuery && window.jQuery.fn.dataTable) {
                        jQuery(document).on('draw.dt', initTooltips);
                    }
                })();
                </script>
                EOT;
            },
            'escaped' => false,
            'visible' => false,
            
        ]);
                
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
        
        // NUEVO CAMPO: Tipo
        CRUD::addField([
            'label'     => "Tipo de Vinculación",
            'type'      => 'select2',
            'name'      => 'tipos_id',
            'entity'    => 'tipo',
            'attribute' => 'nombre',
            'model'     => Tipo::class,
            'wrapper'   => ['class' => 'form-group col-md-4'],
            'allows_null' => true,
        ]);

        // NUEVO CAMPO: Estado Persona (Reemplaza 'estado' y usa la relación)
        CRUD::addField([
            'label'     => "Estado de la Persona",
            'type'      => 'select2',
            'name'      => 'estado_persona_id',
            'entity'    => 'estadoPersona',
            'attribute' => 'nombre',
            'model'     => EstadoPersona::class,
            'wrapper'   => ['class' => 'form-group col-md-4'],
            'allows_null' => true,
            'default' => EstadoPersona::first()->id ?? null,
        ]);
    
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
             // Dejamos un espacio si no tiene permiso, para mantener la grilla
             CRUD::addField(['name' => 'placeholder_ref', 'type' => 'custom_html', 'value' => '', 'wrapper' => ['class' => 'form-group col-md-4']]);
        }
        CRUD::field('referencia_2')->label('Referencia 2')->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::field('caso_id')->label('Caso Especial')->type('select2')
            ->entity('caso')
            ->attribute('nombre')
            ->model(\App\Models\Caso::class)
            ->wrapper(['class' => 'form-group col-md-4']);
    
        // --- FILA 5: Secretaría y Gerencia ---
            // --- FILA 5: Secretaría ---
            CRUD::addField([
                'name' => 'secretaria_id',
                'label' => 'Secretaría',
                'type' => 'select',
                'entity' => 'secretaria',
                'attribute' => 'nombre',
                'model' => \App\Models\Secretaria::class,
                'wrapper' => ['class' => 'form-group col-md-4'],
                'allows_null' => true,
            ]);
        
            CRUD::addField([
                'name' => 'gerencia_id',
                'label' => 'Gerencia',
                'type' => 'select2',
                'entity' => 'gerencia',
                'attribute' => 'nombre',
                'model' => \App\Models\Gerencia::class,
                'wrapper' => ['class' => 'form-group col-md-4'],
                'allows_null' => true,
            ]);
        
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

        
        
        

        CRUD::field('no_tocar')->label('No Tocar')->type('checkbox')->wrapper(['class' => 'form-group col-md-4']);
        
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
                    $html = '<div class="row">'; // Row principal
    
                    // --- 1. FOTO: Ocupa la primera columna (col-md-3) ---
                    if ($entry->foto) {
                        $fotoUrl = asset('storage/'.$entry->foto);
                        // CLASES CLAVE: h-100 en card y card-body para que se estiren verticalmente.
                        $html .= '
                            <div class="mb-2 col-md-3 d-flex align-items-stretch">
                                <div class="border-0 shadow-sm w-100 h-100 card">
                                    <div class="px-3 py-2 text-center card-body h-100 d-flex flex-column justify-content-center">
                                        <small class="text-muted">Foto</small><br>
                                        <a href="'.$fotoUrl.'" target="_blank" class="mt-2 w-100 h-100 d-block">
                                            <img src="'.$fotoUrl.'" alt="Foto" class="rounded img-fluid w-100 h-100" style="object-fit: contain; cursor:pointer;">
                                        </a>
                                    </div>
                                </div>
                            </div>';
                    }
    
                    // 2. CONTENEDOR DE DATOS: Ocupa las columnas restantes (col-md-9)
                    $html .= '<div class="col-md-9">';
                    $html .= '<div class="row">'; // Nueva fila interna para el flujo de 3 columnas
    
                    // Definición de los campos principales
                    $campos = [
                        'Nombre' => $entry->nombre_contratista,
                        'Cédula/NIT' => $entry->cedula_o_nit,
                        'Celular' => $entry->celular,
                        'Género' => $entry->genero,
                        'Vinculacion' => $entry->tipo?->nombre, 
                        'Estado de la Persona' => $entry->estadoPersona?->nombre, 
                        'Nivel Académico' => $entry->nivelAcademico?->nombre,
                        'Profesión/Técnico/Tecnólogo' => $entry->tecnico_tecnologo_profesion,
                        'Especialización' => $entry->especializacion,
                        'Maestría' => $entry->maestria,
                        'Secretaría' => $entry->secretaria?->nombre, 
                        'Gerencia' => $entry->gerencia?->nombre, 
                        'Caso especial' => $entry->caso?->nombre, 
                        
                    ];
    
                    // Manejo de REFERENCIAS MÚLTIPLES (Many-to-Many)
                    if (backpack_user()->hasAnyRole(['admin', 'diana'])) {
                        $referenciasNombres = $entry->referencias->pluck('nombre')->implode('<br>');
                        $campos['Referencia(s)'] = $referenciasNombres 
                            ? '<div class="fw-bold">'.$referenciasNombres.'</div>'
                            : null;
                    }
                    $campos['Referencia 2'] = $entry->referencia_2;
                    // Generación de las tarjetas (todas usan col-md-4 para flujo de 3 columnas en el col-md-9)
                    foreach ($campos as $label => $valor) {
                        $valorDisplay = $valor;
    
                        // Renderizar la tarjeta (col-md-4)
                        $html .= '
                            <div class="mb-2 col-md-4">
                                <div class="border-0 shadow-sm card">
                                    <div class="px-3 py-2 card-body">
                                        <small class="text-muted">'.$label.'</small>
                                        <div class="fw-semibold">'.($valorDisplay ?? '<span class="text-muted">N/A</span>').'</div>
                                    </div>
                                </div>
                            </div>';
                    }
    
                    // PDF (también usa col-md-4)
                    if ($entry->documento_pdf) {
                        $pdfUrl = asset('storage/'.$entry->documento_pdf);
                        $html .= '
                            <div class="mb-2 col-md-4">
                                <div class="border-0 shadow-sm card">
                                    <div class="px-3 py-2 text-center card-body">
                                        <small class="text-muted">Documento PDF</small><br>
                                        <a href="'.$pdfUrl.'" target="_blank" class="mt-2 btn btn-outline-primary btn-sm">Ver PDF</a>
                                    </div>
                                </div>
                            </div>';
                    }
    
                    $html .= '</div>'; // Cierra la fila interna (row)
                    $html .= '</div>'; // Cierra el contenedor col-md-9
    
                    $html .= '</div>'; // Cierra el row principal
                    return $html;
                },
                'escaped' => false,
            ]);
            // Tabla de seguimientos (SE MANTIENE TAL CUAL)
            $this->crud->addColumn([
                'name'     => 'seguimientos',
                'label'    => 'Seguimientos',
                'type'     => 'closure',
                'function' => function ($entry) {
                    if ($entry->seguimientos->isEmpty()) {
                        return '<p class="text-muted">Sin seguimientos registrados.</p>';
                    }
        
                    $html = '<div class="mt-4 card">
                                <div class="text-white card-header bg-primary">Seguimientos</div>
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
                            </div>';
        
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