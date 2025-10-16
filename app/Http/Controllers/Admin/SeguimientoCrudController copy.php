<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SeguimientoRequest;
use App\Imports\SeguimientoImport;
use App\Exports\SeguimientoTemplateExport; // 👈 ¡CLASE FALTANTE AGREGADA!
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel; // NECESARIO para manejar la importación y exportación
use Alert; // Usamos el paquete de alertas
// Usamos clases internas de Excel para la plantilla (aunque ya están dentro de la clase de exportación, las dejamos por si acaso)
use Maatwebsite\Excel\Concerns\FromCollection; 
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeguimientoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
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

        // Filtro por Estado dinámico
        $this->crud->addFilter([
            'name'  => 'estado',
            'type'  => 'text',
            'label' => 'Estado'
        ], false, function ($value) {
            $this->crud->query->where(function ($q) use ($value) {
                $q->where('estado', 'LIKE', "%$value%")
                ->orWhere('estado_contrato', 'LIKE', "%$value%");
            });
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
            return \App\Models\TuModelo::select('anio')->distinct()->pluck('anio', 'anio')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'anio', $value);
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
                    : $entry->estado_contrato;
            },
        ]);
    
        // Campo "observaciones" dinámico
        CRUD::addColumn([
            'name'     => 'observaciones_dynamic',
            'label'    => 'Observaciones',
            'type'     => 'closure',
            'function' => function($entry) {
                return $entry->tipo === 'entrevista'
                    ? $entry->observaciones
                    : $entry->observaciones_contrato;
            },
        ]);
    }
    
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SeguimientoRequest::class);
    
        // Persona
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
                'entrevista' => 'Entrevista'
            ],
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
    
        // -----------------------------
        // CAMPOS DE ENTREVISTA
        // -----------------------------
        CRUD::addField(['name' => 'dependencia', 'label' => 'Dependencia', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 entrevista-field']]);
        CRUD::addField(['name' => 'gerencia', 'label' => 'Gerencia', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 entrevista-field']]);
        CRUD::addField(['name' => 'fecha_entrevista', 'label' => 'Fecha enviado a entrevista', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-4 entrevista-field']]);
        CRUD::addField(['name' => 'estado', 'label' => 'Estado', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 entrevista-field']]);
        CRUD::addField(['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'wrapper' => ['class' => 'form-group col-md-12 entrevista-field']]);
    
        // -----------------------------
        // CAMPOS DE CONTRATO
        // -----------------------------
        CRUD::addField(['name' => 'dependencia', 'label' => 'Dependencia', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'gerencia', 'label' => 'Gerencia', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'anio', 'label' => 'Año', 'type' => 'number', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        // Autorizaciones
        CRUD::addField(['name' => 'aut_despacho', 'label' => 'Autorización Despacho', 'type' => 'checkbox', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'aut_planeacion', 'label' => 'Autorización Planeación', 'type' => 'checkbox', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'aut_administrativa', 'label' => 'Autorización Administrativa', 'type' => 'checkbox', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        CRUD::addField(['name' => 'estado_contrato', 'label' => 'Estado', 'type' => 'select_from_array', 'options' => ['En ejecución'=>'En ejecución','Terminado'=>'Terminado'], 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'numero_contrato', 'label' => '# Contrato', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'fecha_acta_inicio', 'label' => 'Fecha Acta de Inicio', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'fecha_finalizacion', 'label' => 'Fecha Finalización', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        CRUD::addField([
            'name' => 'tiempo_ejecucion_dias',
            'label' => 'Tiempo Ejecución (días)',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field']
        ]);
        CRUD::addField(['name' => 'valor_mensual', 'label' => 'Valor Mensual', 'type' => 'number', 'attributes'=>['step'=>'0.01'], 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        
        CRUD::addField([
            'name' => 'valor_total',
            'label' => 'Valor Total',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field']
        ]);
    
        // Campos Adición
        CRUD::addField(['name' => 'adicion', 'label' => 'Adición', 'type' => 'select_from_array', 'options'=>['SI'=>'SI','NO'=>'NO'], 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'fecha_acta_inicio_adicion', 'label' => 'Fecha Acta Inicio Adición', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'fecha_finalizacion_adicion', 'label' => 'Fecha Finalización Adición', 'type' => 'date', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        CRUD::addField([
            'name' => 'tiempo_ejecucion_dias_adicion',
            'label' => 'Tiempo Ejecución (días) Adición',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field']
        ]);
        CRUD::addField([
            'name' => 'tiempo_total_ejecucion_dias',
            'label' => 'Tiempo Total Ejecución (días)',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field']
        ]);
    
        CRUD::addField(['name' => 'valor_adicion', 'label' => 'Valor Adición', 'type' => 'number', 'attributes'=>['step'=>'0.01'], 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        CRUD::addField([
            'name' => 'valor_total_contrato',
            'label' => 'Valor Total Contrato',
            'type' => 'number',
            'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group col-md-4 contrato-field']
        ]);
        
        CRUD::addField(['name' => 'evaluacion', 'label' => 'Evaluacion', 'type' => 'text', 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
    
        CRUD::addField(['name' => 'continua', 'label' => 'Evaluación Continua', 'type' => 'select_from_array', 'options'=>['SI'=>'SI','NO'=>'NO'], 'wrapper' => ['class' => 'form-group col-md-4 contrato-field']]);
        CRUD::addField(['name' => 'observaciones_contrato', 'label' => 'Observaciones', 'type' => 'textarea', 'wrapper' => ['class' => 'form-group col-md-12 contrato-field']]);
    
        // -----------------------------
        // SCRIPT JS PARA MOSTRAR/OCULTAR
        // -----------------------------
        CRUD::addField([
            'name'  => 'script_tipo',
            'type'  => 'custom_html',
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
            'name'  => 'script_tipo',
            'type'  => 'custom_html',
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
        
                    function toggleAdicion() {
                        var adicion = document.querySelector("[name=adicion]").value;
        
                        // lista de campos de adición
                        var campos = [
                            "fecha_acta_inicio_adicion",
                            "fecha_finalizacion_adicion",
                            "tiempo_ejecucion_dias_adicion",
                            "valor_adicion",
                            "tiempo_total_ejecucion_dias",
                            "valor_total_contrato"
                        ];
        
                        campos.forEach(function(name) {
                            var input = document.querySelector("[name="+name+"]");
                            if (!input) return;
        
                            if (adicion === "NO") {
                                input.setAttribute("readonly", "readonly");
                                input.style.backgroundColor = "#f5f5f5";
                                input.style.cursor = "not-allowed";
                                input.value = ""; // opcional: limpiar si NO aplica
                            } else {
                                input.removeAttribute("readonly");
                                input.style.backgroundColor = "";
                                input.style.cursor = "";
                            }
                        });
                    }
        
                    document.addEventListener("DOMContentLoaded", function() {
                        toggleFields();
                        toggleAdicion();
        
                        document.querySelector("[name=tipo]").addEventListener("change", toggleFields);
                        document.querySelector("[name=adicion]").addEventListener("change", toggleAdicion);
                    });
                </script>
            ',
        ]);
        
    }
    
    

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
