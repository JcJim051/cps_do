<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SeguimientoRequest;
use App\Imports\SeguimientoImport;
use App\Exports\SeguimientoTemplateExport; 
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel; 
use Alert; 
use Maatwebsite\Excel\Concerns\FromCollection; 
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon; // Importamos Carbon para usar today()

class SeguimientoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(\App\Models\Seguimiento::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/seguimiento');
        CRUD::setEntityNameStrings('seguimiento', 'seguimientos');
    }

    protected function setupListOperation(): void
    {
        // 🚨 CORRECCIÓN: Usar el nombre de vista que creamos
        $this->crud->addButtonFromView('top', 'import', 'import_seguimientos_button', 'end'); 
        
        // Filtro por Persona (persona_id)
        $this->crud->addFilter([
            'name'  => 'persona_id',
            'type'  => 'select2',
            'label' => 'Nombre'
        ], function () {
            return \App\Models\Persona::pluck('nombre_contratista', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'persona_id', $value);
        });

        // Filtro por Tipo (entrevista / contrato u otros valores que tenga tu BD)
        $this->crud->addFilter([
            'name'  => 'tipo',
            'type'  => 'dropdown',
            'label' => 'Tipo'
        ], [
            'entrevista' => 'Entrevista',
            'contrato'   => 'Contrato',
            // Agrega más valores si existen
        ], function ($value) {
            $this->crud->addClause('where', 'tipo', $value);
        });

        $this->crud->addFilter([
            'name'  => 'estado_contrato_id',
            'type'  => 'select2',
            'label' => 'Estado Contrato'
        ], function () {
            return \App\Models\Estados::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'estado_contrato_id', $value);
        });
        

        // Filtro por Observaciones dinámicas
        $this->crud->addFilter([
            'name'  => 'observaciones',
            'type'  => 'text',
            'label' => 'Observaciones'
        ], false, function ($value) {
            $this->crud->query->where(function ($q) use ($value) {
                $q->where('observaciones', 'LIKE', "%$value%")
                ->orWhere('observaciones_contrato', 'LIKE', "%$value%");
            });
        });

        // Filtro por Año (anio)
        $this->crud->addFilter([
            'name'  => 'anio',
            'type'  => 'dropdown',
            'label' => 'Año'
        ], function () {
            return \App\Models\Seguimiento::select('anio')->distinct()->pluck('anio', 'anio')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'anio', $value);
        });
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

        
        CRUD::column('persona_id')
            ->label('Nombre')
            ->type('select')
            ->entity('persona')
            ->model(\App\Models\Persona::class)
            ->attribute('nombre_contratista');
    
        CRUD::column('tipo')->label('Tipo');
    
        // Campo "estado" dinámico
        CRUD::addColumn([
            'name'     => 'estado_dynamic',
            'label'    => 'Estado',
            'type'     => 'closure',
            'function' => function($entry) {
                return $entry->tipo === 'entrevista'
                    ? $entry->estado
                    : optional($entry->estadoContrato)->nombre;
            },
        ]);
        
        CRUD::column('anio')->label('Año');
        // Campo "observaciones" dinámico
        CRUD::addColumn([
            'name'     => 'valor_total_contrato',
            'label'    => 'Valor Contrato',
            'type'     => 'closure',
            'function' => function($entry) {
                return '$ ' . number_format($entry->valor_total_contrato, 0, ',', '.');  // Ej: $ 25.000.000
            },
        ]);
        // CRUD::addColumn([
        //     'name'     => 'observaciones_dynamic',
        //     'label'    => 'Observaciones',
        //     'type'     => 'closure',
        //     'function' => function($entry) {
        //         return $entry->tipo === 'entrevista'
        //             ? $entry->observaciones
        //             : $entry->observaciones_contrato;
        //     },
        // ]);
    }

    // ... (El resto de setupCreateOperation y setupUpdateOperation se mantiene igual)

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SeguimientoRequest::class);

        CRUD::addField([
            'name' => 'persona_id',
            'label' => 'Nombre',
            'type' => 'select2',
            'entity' => 'persona',
            'model' => 'App\\Models\\Persona',
            'attribute' => 'nombre_contratista',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Tipo
        CRUD::addField([
            'name' => 'tipo',
            'label' => 'Tipo de Proceso',
            'type' => 'select2_from_array',
            'options' => [
                'contrato' => 'Contrato',
                'entrevista' => 'Entrevista',
            ],
            // Persona
        'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'secretaria_id',
            'label' => 'Secretaría',
            'type' => 'select',
            'entity' => 'secretaria',
            'attribute' => 'nombre',
            'model' => \App\Models\Secretaria::class,
            'wrapper' => ['class' => 'form-group col-md-5'],
            'allows_null' => true,
        ]);
        CRUD::addField([
            'name' => 'gerencia_id',
            'label' => 'Gerencia',
            'type' => 'select2',
            'entity' => 'gerencia',
            'attribute' => 'nombre',
            'model' => \App\Models\Gerencia::class,
            'wrapper' => ['class' => 'form-group col-md-3'],
            'allows_null' => true,
        ]);

        

       
        /**
         * -----------------------------
         * CAMPOS DE ENTREVISTA
         * -----------------------------
         */
        
        CRUD::addField([
            'name' => 'fecha_entrevista',
            'label' => 'Fecha enviado a entrevista',
            'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-6 entrevista-field'],
        ]);
        CRUD::addField([
            'name' => 'estado_id',
            'label' => 'Estado entrevista',
            'type' => 'select2',
            'entity' => 'estado',
            'model' => 'App\\Models\\Estados',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-6 entrevista-field'],
        ]);
        CRUD::addField([
            'name' => 'observaciones',
            'label' => 'Observaciones',
            'type' => 'textarea',
            'wrapper' => ['class' => 'form-group col-md-12 entrevista-field'],
        ]);

        /**
         * -----------------------------
         * CAMPOS DE CONTRATO
         * -----------------------------
         */
        
       

        // Autorizaciones + fechas automáticas
        CRUD::addField([
            'name' => 'estado_contrato_id',
            'label' => 'Estado Contrato',
            'type' => 'select2',
            'entity' => 'estadoContrato',
            'model' => 'App\\Models\\Estados',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);
        CRUD::addField(['name' => 'anio', 'label' => 'Año', 'type' => 'number', 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        
        // ✅ 1. DESPACHO
        CRUD::addField([
            'name' => 'aut_despacho',
            'label' => 'Autorización 1',
            'type' => 'switch',  // visible y rastreable
            'default' => 0,
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        CRUD::addField([
            'name' => 'fecha_aut_despacho',
            'label' => '',
            'type' => 'date',
            'attributes' => [
                
                'style' => 'background-color:#f5f5f5;cursor:not-allowed;',
            ],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        // ✅ 2. PLANEACIÓN
        CRUD::addField([
            'name' => 'aut_planeacion',
            'label' => 'Autorización 2',
            'type' => 'switch',
            'default' => 0,
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        CRUD::addField([
            'name' => 'fecha_aut_planeacion',
            'label' => '',
            'type' => 'date',
            'attributes' => [
                
                'style' => 'background-color:#f5f5f5;cursor:not-allowed;',
            ],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        // ✅ 3. ADMINISTRATIVA
        CRUD::addField([
            'name' => 'aut_administrativa',
            'label' => 'Autorización 3',
            'type' => 'switch',
            'default' => 0,
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        CRUD::addField([
            'name' => 'fecha_aut_administrativa',
            'label' => '',
            'type' => 'date',
            'attributes' => [
                
                'style' => 'background-color:#f5f5f5;cursor:not-allowed;',
            ],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
        ]);

        
        CRUD::addField(['name' => 'numero_contrato', 'label' => '# Contrato', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        CRUD::addField(['name' => 'fecha_acta_inicio', 'label' => 'Fecha Acta de Inicio', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        CRUD::addField(['name' => 'fecha_finalizacion', 'label' => 'Fecha Finalización', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        CRUD::addField(['name' => 'valor_mensual', 'label' => 'Valor Mensual', 'type' => 'number', 'attributes'=>['step'=>'0.01'], 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        CRUD::addField([
            'name' => 'tiempo_ejecucion_dias',
            'label' => 'Tiempo Ejecución (días)',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field']
        ]);CRUD::addField([
            'name' => 'valor_total',
            'label' => 'Valor Total',
            'type' => 'number',
            //'attributes' => ['style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field']
        ]);
        
        
        // Campos Adición
        CRUD::addField(['name' => 'adicion', 'label' => 'Adición', 'type' => 'select_from_array', 'options'=>['SI'=>'SI','NO'=>'NO'], 'wrapper' => ['class' => 'form-group col-md-1 contrato-field']]);
        CRUD::addField(['name' => 'valor_adicion', 'label' => 'Valor Adición', 'type' => 'number', 'attributes'=>['step'=>'0.01'], 'wrapper' => ['class' => 'form-group col-md-2 contrato-field']]);
        CRUD::addField(['name' => 'fecha_acta_inicio_adicion', 'label' => 'Inicio Adición', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-1 contrato-field']]);
        CRUD::addField(['name' => 'fecha_finalizacion_adicion', 'label' => 'Fin Adición', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-1 contrato-field']]);
    
        CRUD::addField([
            'name' => 'tiempo_ejecucion_dias_adicion',
            'label' => 'Adición (días) ',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field']
        ]);
        CRUD::addField([
            'name' => 'tiempo_total_ejecucion_dias',
            'label' => 'Total (días)',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field']
        ]);
    
        
        CRUD::addField([
            'name' => 'valor_total_contrato',
            'label' => 'Valor Total Contrato',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-2 contrato-field']
        ]);

        CRUD::addField([
            'name' => 'evaluacion_id',
            'label' => 'Evaluación',
            'type' => 'select2',
            'entity' => 'evaluacion',
            'model' => 'App\\Models\\Evaluacion',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field'],
        ]);

        CRUD::addField([
            'name' => 'continua',
            'label' => 'Continua',
            'type' => 'select2_from_array',
            'options' => ['SI' => 'SI', 'NO' => 'NO'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field'],
        ]);
        CRUD::addField([
            'name' => 'fuente_id',
            'label' => 'Fuente de Financiacion',
            'type' => 'select2',
            'entity' => 'fuente',
            'attribute' => 'nombre',
            'model' => \App\Models\Fuente::class,
            'wrapper' => ['class' => 'form-group col-md-4'],
            'allows_null' => true,
        ]);

        CRUD::addField(['name' => 'observaciones_contrato', 'label' => 'Observaciones', 'type' => 'textarea', 'wrapper' => ['class' => 'form-group col-md-12 contrato-field']]);

        /**
         * -----------------------------
         * JS: mostrar/ocultar + fechas autorizaciones
         * -----------------------------
         */
        CRUD::addField([
            'name' => 'script_toggle_tipo',
            'type' => 'custom_html',
            'value' => '
                <script>
                    function toggleFields() {
                        var tipo = document.querySelector("[name=tipo]").value;
                        document.querySelectorAll(".contrato-field").forEach(el => {
                            el.style.display = (tipo === "contrato") ? "block" : "none";
                        });
                        document.querySelectorAll(".entrevista-field").forEach(el => {
                            el.style.display = (tipo === "entrevista") ? "block" : "none";
                        });
                    }
        
                    document.addEventListener("DOMContentLoaded", function() {
                        toggleFields();
                        document.querySelector("[name=tipo]").addEventListener("change", toggleFields);
                    });
                </script>
            ',
        ]);
        
       
        CRUD::addField([
            'name' => 'script_autorizaciones',
            'type' => 'custom_html',
            'value' => '
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    console.log("Script de autorizaciones activo");
        
                    function bindFecha(autoName, fechaName) {
                        const checkbox = document.querySelector("[name=\'" + autoName + "\']");
                        const fechaInput = document.querySelector("[name=\'" + fechaName + "\']");
        
                        if (!checkbox || !fechaInput) {
                            console.warn("No encontrados:", autoName, fechaName);
                            return;
                        }
        
                        checkbox.addEventListener("change", function() {
                            console.log("Cambio detectado en:", autoName);
        
                            if (this.checked && !fechaInput.value) {
                                const hoy = new Date().toISOString().split("T")[0];
                                fechaInput.value = hoy;
                                console.log("Fecha asignada:", hoy);
                            }
                        });
                    }
        
                    bindFecha("aut_despacho", "fecha_aut_despacho");
                    bindFecha("aut_planeacion", "fecha_aut_planeacion");
                    bindFecha("aut_administrativa", "fecha_aut_administrativa");
                });
                </script>
            ',
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
                                gerencia.innerHTML = "";
                                data.forEach(item => {
                                    const option = document.createElement("option");
                                    option.value = item.id;
                                    option.text  = item.text;
                                    gerencia.appendChild(option);
                                });
                            });
                    }
    
                    secretaria?.addEventListener("change", function() {
                        cargarGerencias(this.value);
                    });
    
                    // Si ya hay valor al cargar (edición)
                    if (secretaria?.value) {
                        cargarGerencias(secretaria.value);
                    }
                });
            </script>',
        ]);
        
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    public function update(Request $request)
    {
        // Autocompletar fechas si el switch está encendido y la fecha viene vacía
        if ($request->input('tipo') === 'contrato') {
            foreach (['despacho','planeacion','administrativa'] as $suf) {
                $auto  = "aut_$suf";
                $fecha = "fecha_aut_$suf";
    
                // Para fields tipo "switch", boolean() reconoce "1"/"0" correctamente
                if ($request->boolean($auto) && !$request->filled($fecha)) {
                    $request->merge([$fecha => Carbon::now('America/Bogota')->format('Y-m-d')]);
                }
            }
        }
    
        // Llama al método original del trait
        return $this->traitUpdate();
    }
    
    /**
     * Muestra la vista con el formulario para subir el archivo de importación.
     */
    public function importForm()
    {
        $this->crud->hasAccessOrFail('create');
        
        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Importar Seguimientos';
        $this->data['ruta_post'] = url($this->crud->route . '/import'); 
        
        // 🔑 CLAVE 1: Recuperar fallos de la sesión para mostrarlos
        $this->data['importFailures'] = session('importFailures', []); 

        // 🔑 CLAVE 2: Usar la vista centralizada de errores (la definimos abajo)
        return view('admin.seguimiento.import', $this->data); 
    }

    /**
     * Procesa el archivo de importación subido.
     */
    public function import(Request $request)
    {
        $this->crud->hasAccessOrFail('create');
        
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv',
        ], [
            'file.required' => 'Debe seleccionar un archivo.',
            'file.mimes' => 'El archivo debe ser de tipo Excel (.xls, .xlsx) o CSV.',
        ]);

        $import = new SeguimientoImport; // 🔑 CLAVE 3: Instanciar la clase de importación

        try {
            // 4. Ejecutar la importación (Maatwebsite usa el objeto instanciado)
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));
            
            // 🔑 CLAVE 5: Capturar los fallos (validación + lógica)
            $failures = $import->logicFailures; 
            
            if (empty($failures)) {
                Alert::success('¡Importación de Seguimientos completada exitosamente!')->flash();
                return redirect($this->crud->route);
            } else {
                $totalFailures = count($failures);
                Alert::warning("Importación finalizada con {$totalFailures} fila(s) no importada(s). Revise el listado a continuación.")->flash();
                
                // 🔑 CLAVE 6: Redirigir al formulario y PASAR los fallos a la sesión
                return redirect($this->crud->route . '/import')->with('importFailures', $failures);
            }
            
        } catch (\Throwable $e) {
            Log::error("Error fatal en la importación de Seguimientos: " . $e->getMessage());
            Alert::error("Ocurrió un error grave en el servidor. Revise el log de Laravel: " . $e->getMessage())->flash();
            return back();
        }
    }

    /**
     * Genera y descarga el archivo de plantilla de Seguimientos.
     */
    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new SeguimientoTemplateExport, 'plantilla_seguimientos.xlsx');
    }

    protected function setupShowOperation(): void
    {
        $this->crud->set('show.setFromDb', false);
        $this->crud->set('show.contentClass', 'container-fluid');
        

        $this->crud->addColumn([
            'name'     => 'detalle_seguimiento',
            'label'    => 'Detalle del Seguimiento',
            'type'     => 'closure',
            'escaped'  => false,
            'function' => function ($entry) {

                // Persona relacionada
                $persona = $entry->persona;
                $personaCampos = [
                    'Nombre' => $persona?->nombre_contratista,
                    'Cédula/NIT' => $persona?->cedula_o_nit,
                    'Celular' => $persona?->celular,
                ];

                // Campos generales (para ambos tipos)
                $generales = [
                    'Tipo' => ucfirst($entry->tipo),
                    'Secretaría' => $entry->secretaria?->nombre,
                    'Gerencia' => $entry->gerencia?->nombre,
                   
                ];

                // Campos para ENTREVISTA
                $entrevista = [
                    'Fecha Entrevista' => $entry->fecha_entrevista,
                    'Estado (Entrevista)' => $entry->estado?->nombre,
                    'Observaciones' => $entry->observaciones,
                ];

                // Campos para CONTRATO
                $contrato = [
                    'Año' => $entry->anio,
                    'Fuente' => $entry->fuente?->nombre,
                    'Número Contrato' => $entry->numero_contrato,
                    'Valor Mensual' => $entry->valor_mensual,
                    'Fecha Acta Inicio' => $entry->fecha_acta_inicio,
                    'Fecha Finalización' => $entry->fecha_finalizacion,
                    'Tiempo Total Ejecución' => $entry->tiempo_total_ejecucion_dias,
                    'Valor Total Contrato' => $entry->valor_total_contrato,
                    'Estado Contrato' => $entry->estadoContrato?->nombre,
                    'Aut. Despacho' => $entry->aut_despacho ? '✅' : '❌',
                    'Aut. Planeación' => $entry->aut_planeacion ? '✅' : '❌',
                    'Aut. Administrativa' => $entry->aut_administrativa ? '✅' : '❌',

                    'Tiempo Ejecución (días)' => $entry->tiempo_ejecucion_dias,
                    'Valor Total' => $entry->valor_total,
                    'Adición' => $entry->adicion,
                    'Fecha Acta Inicio Adic.' => $entry->fecha_acta_inicio_adicion,
                    'Fecha Finalización Adic.' => $entry->fecha_finalizacion_adicion,
                    'Tiempo Ejecución Adic.' => $entry->tiempo_ejecucion_dias_adicion,
                    'Valor Adición' => $entry->valor_adicion,
                    'Continúa' => $entry->continua ? '✅' : '❌',
                    'Observaciones Contrato' => $entry->observaciones_contrato,
                    
                ];

                // Render genérico
                $html = "<div class='row'>";

                // 🔹 Bloque: Persona
                $html .= "<div class='col-12'><h5 class='mt-4 text-primary'>Datos de la Persona</h5><div class='row'>";
                foreach ($personaCampos as $label => $valor) {
                    $html .= self::renderCard($label, $valor);
                }
                $html .= "</div></div>";

                // 🔹 Bloque: Generales
                $html .= "<div class='col-12'><h5 class='mt-4 text-primary'>Datos Generales</h5><div class='row'>";
                foreach ($generales as $label => $valor) {
                    $html .= self::renderCard($label, $valor);
                }
                $html .= "</div></div>";

                // 🔹 Bloque según tipo
                if ($entry->tipo === 'contrato') {
                    $html .= "<div class='col-12'><h5 class='mt-4 text-success'>Información del Contrato</h5><div class='row'>";
                    foreach ($contrato as $label => $valor) {
                        $html .= self::renderCard($label, $valor);
                    }
                    $html .= "</div></div>";
                } elseif ($entry->tipo === 'entrevista') {
                    $html .= "<div class='col-12'><h5 class='mt-4 text-info'>Información de la Entrevista</h5><div class='row'>";
                    foreach ($entrevista as $label => $valor) {
                        $html .= self::renderCard($label, $valor);
                    }
                    $html .= "</div></div>";
                }

                $html .= "</div>";
                return $html;
            }
        ]);
    }

    // ✅ Helper para evitar repetir código
    protected static function renderCard($label, $valor)
    {
        return '
            <div class="mb-2 col-md-3">
                <div class="border-0 shadow-sm card">
                    <div class="px-3 py-2 card-body">
                        <small class="text-muted">'.$label.'</small>
                        <div class="fw-semibold">'.($valor ?? '<span class="text-muted">N/A</span>').'</div>
                    </div>
                </div>
            </div>';
    }



}
