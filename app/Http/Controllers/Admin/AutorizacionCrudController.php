<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AutorizacionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request; // ✅ ESTA es la correcta

class AutorizacionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    // No necesitamos DeleteOperation si no quieres eliminar

    public function setup(): void
    {
        CRUD::setModel(\App\Models\Autorizacion::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/autorizacion');
        CRUD::setEntityNameStrings('Autorización', 'Autorizaciones');

        $this->middleware(['role:administrativa,bancos,diana,admin']);
    }

    protected function setupListOperation(): void
    {
        $this->crud->addClause('where', 'tipo', 'contrato');

        // Filtro por Secretaría (secretaria_id)
        $this->crud->addFilter([
            'name'  => 'secretaria_id',
            'type'  => 'select2',
            'label' => 'Secretaría'
        ], function () {
            // Retorna las opciones para el dropdown
            return \App\Models\Secretaria::pluck('convencion', 'id')->toArray();
        }, function ($value) {
            // Aplica el filtro
            $this->crud->addClause('where', 'secretaria_id', $value);
        });

        // Filtro por Persona (persona_id)
        $this->crud->addFilter([
            'name'  => 'persona_id',
            'type'  => 'select2',
            'label' => 'Persona'
        ], function () {
            return \App\Models\Persona::pluck('nombre_contratista', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'persona_id', $value);
        });

        // Filtro por Valor Total (rango numérico)
        $this->crud->addFilter([
            'type'  => 'range',
            'name'  => 'valor_total',
            'label' => 'Valor Total'
        ],
        false,
        function ($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'valor_total', '>=', (float)$range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'valor_total', '<=', (float)$range->to);
            }
        });

        // Filtro Autorizaciones (Aut 1, 2, 3)
        $this->crud->addFilter([
            'name'  => 'autorizaciones',
            'type'  => 'dropdown',
            'label' => 'Autorizaciones'
        ], [
            'aut_despacho'     => 'Aut 1',
            'aut_planeacion'   => 'Aut 2',
            'aut_administrativa' => 'Aut 3'
        ], function ($value) {
            $this->crud->addClause('where', $value, 1); // Asumiendo que es 1 = autorizado
        });



        CRUD::column('secretaria_id')
            ->label('Secretaría')
            ->entity('secretaria')
            ->model(\App\Models\Secretaria::class)
            ->attribute('convencion');

        

       CRUD::column('persona_id')
            ->label('Nombre')
            
            ->entity('persona')
            ->model(\App\Models\Persona::class)
            ->attribute('nombre_contratista');

        $this->crud->addColumn([
            'name' => 'valor_total',
            'label' => 'Valor Total',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
        ]);

        $this->crud->addColumn([
            'name' => 'aut_despacho',
            'label' => 'Aut 1',
            'type' => 'model_function',
            'function_name' => 'getAutDespachoIcon',
            'escaped' => false, // <--- importante
        ]);
        $this->crud->addColumn([
            'name' => 'aut_planeacion',
            'label' => 'Aut 2',
            'type' => 'model_function',
            'function_name' => 'getAutPlaneacionIcon',
            'escaped' => false, // <--- importante
        ]);
        $this->crud->addColumn([
            'name' => 'aut_administrativa',
            'label' => 'Aut 3',
            'type' => 'model_function',
            'function_name' => 'getAutAdministrativaIcon',
            'escaped' => false, // <--- importante
        ]);
        $this->crud->removeButtons(['create', 'show', 'delete', 'update']);
        $this->crud->addButtonFromView('line', 'update', 'update_new_tab', 'end');
        
        
    }
    protected function generateAuthorizationCards($entry): string
    {
        // Define los datos a mostrar en las tarjetas
        $campos = [
            'Nombre Contratista' => $entry->persona->nombre_contratista ?? 'N/A',
            'Cédula/NIT' => $entry->persona->cedula_o_nit ?? 'N/A',
            'Secretaría' => $entry->secretaria->nombre ?? 'N/A',
            'Gerencia' => $entry->gerencia->nombre ?? 'N/A',
            'Fuente Financiación' => $entry->fuente->nombre ?? 'N/A',
            'Valor Mensual' => '$'.number_format($entry->valor_mensual, 2, ',', '.') ?? 'N/A',
            'Fecha Acta de Inicio' => $entry->fecha_acta_inicio ?? 'N/A',
            'Fecha Finalización' => $entry->fecha_finalizacion ?? 'N/A',
            'Tiempo Ejecución (días)' => $entry->tiempo_ejecucion_dias ?? 'N/A',
            'Valor Total' => '$'.number_format($entry->valor_total, 2, ',', '.') ?? 'N/A',
            'Adición (días)' => $entry->tiempo_ejecucion_dias_adicion ?? '0',
            'Valor Total Contrato' => '$'.number_format($entry->valor_total_contrato, 2, ',', '.') ?? 'N/A',
        ];

        $html = '<div class="pt-3 row">';
        
        foreach ($campos as $label => $valor) {
            // SOLUCIÓN MEJORADA: Usamos col-6 para móvil (2 por fila) y col-md-3 para desktop (4 por fila),
            // lo que resulta en un diseño más espacioso y legible (3 filas en total para 12 campos).
            $html .= '
                <div class="mb-3 col-6 col-md-3"> 
                    <div class="border-0 shadow-sm card h-100">
                        <div class="px-3 py-2 card-body">
                            <small class="text-muted text-uppercase fw-normal d-block text-truncate" title="'.$label.'">'.$label.'</small>
                            <div class="fw-bold fs-6">'.$valor.'</div>
                        </div>
                    </div>
                </div>';
        }
        
        $html .= '</div>';

        return $html;
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(AutorizacionRequest::class);
        
        $entry = $this->crud->getCurrentEntry();
        $user = backpack_user();

        // 1. Mostrar campos informativos en formato tarjeta (SOLO LECTURA)
        // Usa la función helper que el usuario confirmó que tenía la lógica correcta de col-md-3
        CRUD::addField([
            'name' => 'datos_autorizacion_read_only',
            'label' => 'Información del Contrato',
            'type' => 'custom_html',
            'value' => $this->generateAuthorizationCards($entry),
            'wrapper' => ['class' => 'form-group col-12'], 
        ]);

       

        /**
         * -----------------------------
         * CAMPOS DE CONTRATO
         * -----------------------------
         */
        

       
         // ✅ 1. DESPACHO
         $entry = $this->crud->getCurrentEntry(); // solo disponible en Update

         // ----------------- 1. DESPACHO -----------------
         $canEditDespacho = backpack_user()->hasRole(['diana','admin']);
         if ($canEditDespacho) {
             CRUD::addField([
                 'name' => 'aut_despacho',
                 'label' => 'Autorización 1',
                 'type' => 'switch',
                 'default' => 0,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         } else {
             $value = $entry ? ($entry->aut_despacho ? '<div style="color:green; font-size:1.5rem;">Aut 1 ✔</div>' : '<div style="color:red; font-size:1.5rem;">Aut 1 ✖</div>') : '';
             CRUD::addField([
                 'name' => 'aut_despacho',
                 'label' => 'Autorización 1',
                 'type' => 'custom_html',
                 'value' => $value,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         }
         CRUD::addField([
             'name' => 'fecha_aut_despacho',
             'label' => '',
             'type' => 'date',
             'attributes' => ['style'=>'background-color:#f5f5f5;cursor:not-allowed;', 'readonly' => true],
             'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
         ]);
         
         // ----------------- 2. PLANEACIÓN -----------------
         $canEditPlaneacion = backpack_user()->hasRole('bancos') || backpack_user()->hasRole(['diana','admin']);
         if ($canEditPlaneacion) {
             CRUD::addField([
                 'name' => 'aut_planeacion',
                 'label' => 'Autorización 2',
                 'type' => 'switch',
                 'default' => 0,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         } else {
             $value = $entry ? ($entry->aut_planeacion ? '<div style="color:green; font-size:1.5rem;">Aut 2 ✔</div>' : '<div style="color:red; font-size:1.5rem;">Aut 2 ✖</div>') : '';
             CRUD::addField([
                 'name' => 'aut_planeacion',
                 'label' => 'Autorización 2',
                 'type' => 'custom_html',
                 'value' => $value,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         }
         CRUD::addField([
             'name' => 'fecha_aut_planeacion',
             'label' => '',
             'type' => 'date',
             'attributes' => ['style'=>'background-color:#f5f5f5;cursor:not-allowed;', 'readonly' => true],
             'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
         ]);
         
         // ----------------- 3. ADMINISTRATIVA -----------------
         $canEditAdministrativa = backpack_user()->hasRole('administrativa') || backpack_user()->hasRole(['diana','admin']);
         if ($canEditAdministrativa) {
             CRUD::addField([
                 'name' => 'aut_administrativa',
                 'label' => 'Autorización 3',
                 'type' => 'switch',
                 'default' => 0,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         } else {
             $value = $entry ? ($entry->aut_administrativa ? '<div style="color:green; font-size:1.5rem;">Aut 3 ✔</div>' : '<div style="color:red; font-size:1.5rem;">Aut 3 ✖</div>') : '';
             CRUD::addField([
                 'name' => 'aut_administrativa',
                 'label' => 'Autorización 3',
                 'type' => 'custom_html',
                 'value' => $value,
                 'wrapper' => ['class' => 'form-group col-md-2 contrato-field text-center'],
             ]);
         }
         CRUD::addField([
             'name' => 'fecha_aut_administrativa',
             'label' => '',
             'type' => 'date',
             'attributes' => ['style'=>'background-color:#f5f5f5;cursor:not-allowed;', 'readonly' => true],
             'wrapper' => ['class' => 'form-group col-md-2 contrato-field'],
         ]);
         

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

    

    // Funciones de iconos
    public function getAutDespachoIcon($entry)
    {
        return $entry->aut_despacho ? '<span style="color:green;">✔</span>' : '<span style="color:red;">✖</span>';
    }

    public function getAutPlaneacionIcon($entry)
    {
        return $entry->aut_planeacion ? '<span style="color:green;">✔</span>' : '<span style="color:red;">✖</span>';
    }

    public function getAutAdministrativaIcon($entry)
    {
        return $entry->aut_administrativa ? '<span style="color:green;">✔</span>' : '<span style="color:red;">✖</span>';
    }
    public function update(Request $request)
    {
        foreach (['despacho','planeacion','administrativa'] as $suf) {
            $auto  = "aut_$suf";
            $fecha = "fecha_aut_$suf";

            if ($request->boolean($auto) && !$request->filled($fecha)) {
                $request->merge([$fecha => now()->format('Y-m-d')]);
            }
        }

        return $this->traitUpdate();
    }
    public function store()
    {
        $data = $this->crud->getRequest()->all();
        $now = Carbon::now()->toDateString();

        if (isset($data['aut_despacho']) && backpack_user()->hasRole('diana')) {
            $data['fecha_aut_despacho'] = $now;
        }
        if (isset($data['aut_planeacion']) && backpack_user()->hasRole('bancos')) {
            $data['fecha_aut_planeacion'] = $now;
        }
        if (isset($data['aut_administrativa']) && backpack_user()->hasRole('administrativa')) {
            $data['fecha_aut_administrativa'] = $now;
        }

        $this->crud->getRequest()->replace($data);

        return $this->traitStore();
    }

}
