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
use App\Exports\SeguimientoExport;
use App\Models\Persona;
use App\Services\SeguimientoFilterService;

class SeguimientoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(\App\Models\Seguimiento::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/seguimiento');
        CRUD::setEntityNameStrings('seguimiento cto', 'seguimientos cto');
    
    }

    protected function setupListOperation(): void
    {
        // Botón superior
       
        $this->crud->addButtonFromView('top', 'import', 'import_seguimientos_button', 'end');
        $this->crud->addButtonFromView('top', 'export_excel', 'buttons.export_excel', 'end');
        $this->crud->enableExportButtons();
       

        // === FILTROS ===
        $filterService = app(SeguimientoFilterService::class);
        $selectedPeopleIds = $filterService->normalizeListRequest($this->crud->getRequest());
        $selectedPeople = Persona::query()
            ->whereIn('id', $selectedPeopleIds)
            ->orderBy('nombre_contratista')
            ->get(['id', 'nombre_contratista', 'cedula_o_nit'])
            ->mapWithKeys(fn (Persona $persona) => [
                $persona->id => trim($persona->nombre_contratista.' — '.($persona->cedula_o_nit ?: 'Sin documento')),
            ])
            ->all();

        $this->crud->addFilter([
            'name'  => 'personas',
            'type'  => 'select2_ajax_multiple',
            'label' => 'Personas',
            'placeholder' => 'Buscar por nombre o cédula...',
            'minimum_input_length' => 2,
            'select_attribute' => 'display_name',
            'select_key' => 'id',
            'selected_options' => $selectedPeople,
        ], route('seguimiento.fetch-personas'), function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->addClause('whereIn', 'persona_id', $ids);
            }
        });

        $this->crud->addFilter([
            'name'  => 'filtro_referencia',
            'type'  => 'select2_multiple',
            'label' => 'Referencia',
            'placeholder' => 'Seleccione referencias',
        ], function () {
            return \App\Models\Referencia::orderBy('nombre')->pluck('nombre', 'id')->toArray();
        }, function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->query->whereHas('persona.referencias', function ($query) use ($ids) {
                    $query->whereIn('referencias.id', $ids);
                });
            }
        });

        // Estado contrato
        $this->crud->addFilter([
            'name'  => 'estado_contrato_id',
            'type'  => 'select2_multiple',
            'label' => 'Estado Contrato',
            'placeholder' => 'Seleccione estados',
        ], function () {
            return \App\Models\Estados::orderBy('nombre')->pluck('nombre', 'id')->toArray();
        }, function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->addClause('whereIn', 'estado_contrato_id', $ids);
            }
        });

        // Filtro por observaciones
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

        // Año
        $this->crud->addFilter([
            'name'  => 'anio',
            'type'  => 'select2_multiple',
            'label' => 'Año',
            'placeholder' => 'Seleccione años',
        ], function () {
            return \App\Models\Seguimiento::query()
                ->whereNotNull('anio')
                ->select('anio')
                ->distinct()
                ->orderByDesc('anio')
                ->pluck('anio', 'anio')
                ->toArray();
        }, function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->addClause('whereIn', 'anio', $ids);
            }
        });

        // Secretaría
        $this->crud->addFilter([
            'name'  => 'secretaria_id',
            'type'  => 'select2_multiple',
            'label' => 'Secretaría',
            'placeholder' => 'Seleccione secretarías',
        ], function () {
            return \App\Models\Secretaria::orderBy('nombre')->pluck('nombre', 'id')->toArray();
        }, function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->addClause('whereIn', 'secretaria_id', $ids);
            }
        });

        // Gerencia
        $this->crud->addFilter([
            'name'  => 'gerencia_id',
            'type'  => 'select2_multiple',
            'label' => 'Gerencia',
            'placeholder' => 'Seleccione gerencias',
        ], function () {
            return \App\Models\Gerencia::orderBy('nombre')->pluck('nombre', 'id')->toArray();
        }, function ($value) use ($filterService) {
            $ids = $filterService->ids($value);
            if ($ids !== []) {
                $this->crud->addClause('whereIn', 'gerencia_id', $ids);
            }
        });


        $this->crud->setColumns([
            [
                'name'  => 'secretaria',
                'label' => 'Dependencia',
                'type'  => 'relationship',
                'attribute' => 'nombre',
                'visibleInExport' => false,
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhereHas('secretaria', function ($q) use ($searchTerm) {
                        $q->where('nombre', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'min-width:100px; max-width:150px; white-space:normal; line-height:1.2;',
                    'title' => '{{ $entry->secretaria->nombre ?? "" }}',
                ],
            ],
            [
                'name' => 'persona_id',
                'label' => 'Nombre',
                'type' => 'relationship',
                'attribute' => 'nombre_contratista',
                'limit' => 100,
                'visibleInExport' => false,
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhereHas('persona', function ($q) use ($searchTerm) {
                        $q->where('nombre_contratista', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'class' => 'integra-person-name',
                    'title' => '{{$entry->persona->nombre_contratista ?? ""}}'
                ],
            ],
            [
                'name' => 'persona.cedula_o_nit',
                'label' => 'Cédula',
                'type' => 'relationship',
                'visibleInExport' => false,
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhereHas('persona', function ($q) use ($searchTerm) {
                        $q->where('cedula_o_nit', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'min-width:78px; white-space:nowrap;',
                    'title' => '{{$entry->persona->cedula_o_nit ?? ""}}'
                ],
            ],
            [
                'name' => 'anio',
                'label' => 'Año',
                'type' => 'text',
                'visibleInExport' => false,
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere('anio', 'like', "%{$searchTerm}%");
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:60px; text-align:center;'
                ],
            ],
            [
                'name' => 'estado_dynamic',
                'label' => 'Estado',
                'type' => 'closure',
                'visibleInExport' => false,
                'function' => function($entry) {
                    return $entry->tipo === 'entrevista'
                        ? $entry->estado
                        : optional($entry->estadoContrato)->nombre;
                },
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhereHas('estadoContrato', function ($q) use ($searchTerm) {
                        $q->where('nombre', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;',
                    'title' => '{{$entry->tipo === "entrevista" ? $entry->estado : optional($entry->estadoContrato)->nombre}}'
                ],
            ],
            [
                'name' => 'numero_contrato',
                'label' => 'Cto',
                'type' => 'text',
                'visibleInExport' => false,
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere('numero_contrato', 'like', "%{$searchTerm}%");
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:75px; text-align:center; white-space:normal; line-height:1.2;'
                ],
            ],
            [
                'name'  => 'fecha_acta_inicio',
                'label' => 'Inicio',
                'type'  => 'date',
                'visibleInExport' => false,
                'format' => 'DD/MM/YYYY',
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere('fecha_acta_inicio', 'like', "%{$searchTerm}%");
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:90px; text-align:center;'
                ],
            ],
            [
                'name'  => 'fecha_finalizacion_vigente',
                'label' => 'Finalización',
                'type'  => 'closure',
                'visibleInExport' => false,
                'escaped' => false,
                'function' => function ($entry) {
                    $vigente = $entry->fecha_finalizacion_vigente;
                    if (!$vigente) return '';

                    $html = '<strong>'.e($vigente->format('d/m/Y')).'</strong>';
                    if ($entry->fecha_finalizacion && !$entry->fecha_finalizacion->equalTo($vigente)) {
                        $html .= '<br><small class="text-muted">Inicial: '.e($entry->fecha_finalizacion->format('d/m/Y')).'</small>';
                    }
                    return $html;
                },
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere(function ($dateQuery) use ($searchTerm) {
                        $dateQuery->where('fecha_finalizacion', 'like', "%{$searchTerm}%")
                            ->orWhere('fecha_finalizacion_adicion', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:90px; text-align:center;'
                ],
            ],
            [
                'name'  => 'valor_mensual_visible',
                'label' => 'Honorarios',
                'type'  => 'closure',
                'visibleInExport' => false,
                'function' => fn ($entry) => $entry->valor_mensual === null
                    ? ''
                    : '$ '.number_format((float) $entry->valor_mensual, 0, ',', '.'),
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere('valor_mensual', 'like', "%{$searchTerm}%");
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'min-width:78px; white-space:nowrap; text-align:right;'
                ],
            ],
            [
                'name'  => 'tiempo_total_vigente_dias',
                'label' => 'Tiempo',
                'type'  => 'closure',
                'visibleInExport' => false,
                'escaped' => false,
                'function' => function ($entry) {
                    $calendario = $entry->tiempo_total_vigente_dias;
                    if ($calendario === null) return '';

                    $html = '<strong>'.e($calendario).' días</strong>';
                    $efectivo = $entry->tiempo_total_ejecucion_dias;
                    if ($entry->tiempo_total_calendario_dias !== null && $efectivo !== null && (int) $efectivo !== $calendario) {
                        $html .= '<br><small class="text-muted">Ejecución: '.e((int) $efectivo).' días</small>';
                    }
                    return $html;
                },
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere(function ($timeQuery) use ($searchTerm) {
                        $timeQuery->where('tiempo_total_calendario_dias', 'like', "%{$searchTerm}%")
                            ->orWhere('tiempo_total_ejecucion_dias', 'like', "%{$searchTerm}%")
                            ->orWhere('tiempo_ejecucion_dias', 'like', "%{$searchTerm}%");
                    });
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'max-width:55px; text-align:center;'
                ],
            ],
            [
                'name'  => 'valor_total_contrato',
                'label' => 'Valor Total',
                'type'  => 'closure',
                'visibleInExport' => false,
                'function' => fn ($entry) => $entry->valor_total_contrato === null
                    ? ''
                    : '$ '.number_format((float) $entry->valor_total_contrato, 0, ',', '.'),
                'searchLogic' => function ($query, $column, $searchTerm) {
                    $query->orWhere('valor_total_contrato', 'like', "%{$searchTerm}%");
                },
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'min-width:84px; white-space:nowrap; text-align:right;',
                    'title' => '{{$entry->valor_total_contrato}}'
                ],
            ],
            [
                'name'  => 'persona.referencias',
                'label' => 'Referencias',
                'type'  => 'closure',
                'visibleInExport' => false,
            
                'function' => function ($entry) {
                    return $entry->persona?->referencias
                        ?->pluck('nombre')
                        ?->implode(', ') ?? '';
                },
                'escaped' => true,
                'wrapper' => [
                    'element' => 'div',
                    'style' => 'min-width:90px; max-width:145px; white-space:normal; line-height:1.2;',
                ],
            ],
            // Campos solo para export DataTable (ocultos en listado)
            [
                'name' => 'referencias_export',
                'label' => 'Referencias',
                'type' => 'closure',
                'function' => function ($entry) {
                    return $entry->persona?->referencias?->pluck('nombre')->implode(', ') ?? '';
                },
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'cedula_export',
                'label' => 'Cédula / NIT',
                'type' => 'closure',
                'function' => fn($entry) => $entry->persona->cedula_o_nit ?? '',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'nombre_export',
                'label' => 'Nombre',
                'type' => 'closure',
                'function' => fn($entry) => $entry->persona->nombre_contratista ?? '',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'cto_export',
                'label' => 'Cto',
                'type' => 'closure',
                'function' => fn($entry) => $entry->numero_contrato,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'anio_export',
                'label' => 'Año',
                'type' => 'closure',
                'function' => fn($entry) => $entry->anio,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'estado_export',
                'label' => 'Estado',
                'type' => 'closure',
                'function' => fn($entry) => $entry->tipo === 'entrevista'
                    ? ($entry->estado ?? '')
                    : (optional($entry->estadoContrato)->nombre ?? ''),
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'inicio_export',
                'label' => 'Inicio',
                'type' => 'closure',
                'function' => fn($entry) => optional($entry->fecha_acta_inicio)?->format('Y-m-d'),
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'finalizacion_export',
                'label' => 'Finalización Vigente',
                'type' => 'closure',
                'function' => fn($entry) => optional($entry->fecha_finalizacion_vigente)?->format('Y-m-d'),
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'finalizacion_inicial_export',
                'label' => 'Finalización Inicial',
                'type' => 'closure',
                'function' => fn($entry) => optional($entry->fecha_finalizacion)?->format('Y-m-d'),
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'tiempo_ejecucion_dias',
                'label' => 'Dias Inicial',
                'type' => 'number',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'valor_mensual',
                'label' => 'Valor Mensual Inicial',
                'type' => 'number',
                'decimals' => 0,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'valor_total',
                'label' => 'Valor Total Inicial',
                'type' => 'number',
                'decimals' => 0,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'adicion',
                'label' => 'Tiene Adicion',
                'type' => 'text',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'fecha_acta_inicio_adicion',
                'label' => 'Fecha Inicio Adicion',
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'fecha_finalizacion_adicion',
                'label' => 'Fecha Fin Adicion',
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'tiempo_ejecucion_dias_adicion',
                'label' => 'Dias Adicion',
                'type' => 'number',
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'valor_adicion',
                'label' => 'Valor Adicion',
                'type' => 'number',
                'decimals' => 0,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'total_dias_export',
                'label' => 'Total Días Calendario',
                'type' => 'closure',
                'function' => fn($entry) => $entry->tiempo_total_vigente_dias,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'total_dias_ejecucion_export',
                'label' => 'Total Días Ejecución',
                'type' => 'closure',
                'function' => fn($entry) => $entry->tiempo_total_ejecucion_dias,
                'exportOnlyColumn' => true,
            ],
            [
                'name' => 'valor_total_export',
                'label' => 'Valor Total',
                'type' => 'closure',
                'function' => fn($entry) => $entry->valor_total_contrato,
                'exportOnlyColumn' => true,
            ],
            [
                'name'  => 'secretaria_export',
                'label' => 'Secretaría',
                'type'  => 'closure',
                'function' => fn($entry) => $entry->secretaria->nombre ?? '',
                'exportOnlyColumn' => true,
            ],
            [
                'name'  => 'gerencia_export',
                'label' => 'Gerencia',
                'type'  => 'closure',
                'function' => fn($entry) => $entry->gerencia->nombre ?? '',
                'exportOnlyColumn' => true,
            ],
            [
                'name'  => 'fuente_export',
                'label' => 'Fuente de Financiación',
                'type'  => 'closure',
                'function' => fn($entry) => $entry->fuente->nombre ?? '',
                'exportOnlyColumn' => true,
            ],
         
        ]);

        
         

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
            // 'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
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
            // 'attributes' => ['readonly'=>'readonly','style'=>'background-color:#f5f5f5;cursor:not-allowed;'],
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
    
                    function cargarGerencias(secretariaId, selectedId = null) {
                        if (!gerencia) return;
                        if (!secretariaId) {
                            gerencia.innerHTML = "";
                            return;
                        }

                        fetch("/admin/gerencias-por-secretaria/" + secretariaId)
                            .then(res => res.json())
                            .then(data => {
                                const currentText = gerencia.options[gerencia.selectedIndex]?.text || "";
                                gerencia.innerHTML = "";

                                data.forEach(item => {
                                    const option = document.createElement("option");
                                    option.value = item.id;
                                    option.text  = item.text;
                                    if (selectedId && String(item.id) === String(selectedId)) {
                                        option.selected = true;
                                    }
                                    gerencia.appendChild(option);
                                });

                                // Si no vino en catálogo pero existía valor en edición, lo conservamos visualmente.
                                if (selectedId && !Array.from(gerencia.options).some(o => String(o.value) === String(selectedId))) {
                                    const fallback = document.createElement("option");
                                    fallback.value = selectedId;
                                    fallback.text = currentText || ("Gerencia " + selectedId);
                                    fallback.selected = true;
                                    gerencia.appendChild(fallback);
                                }

                                gerencia.dispatchEvent(new Event("change"));
                            });
                    }
    
                    secretaria?.addEventListener("change", function() {
                        // Cambio manual de secretaría: recargar sin forzar selección previa.
                        cargarGerencias(this.value, null);
                    });
    
                    // Si ya hay valor al cargar (edición)
                    if (secretaria?.value) {
                        const selectedGerencia = gerencia?.value || null;
                        cargarGerencias(secretaria.value, selectedGerencia);
                    }
                });
            </script>',
        ]);
        
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function applyAuthorizationDates(Request $request): void
    {
        if ($request->input('tipo') !== 'contrato') {
            return;
        }

        foreach (['despacho', 'planeacion', 'administrativa'] as $suffix) {
            $auto = "aut_$suffix";
            $fecha = "fecha_aut_$suffix";

            if ($request->boolean($auto) && !$request->filled($fecha)) {
                $request->merge([$fecha => Carbon::now('America/Bogota')->format('Y-m-d')]);
            }
        }
    }

    public function store()
    {
        $request = $this->crud->getRequest() ?? request();
        $this->applyAuthorizationDates($request);

        return $this->traitStore();
    }

    public function update(Request $request)
    {
        $this->applyAuthorizationDates($request);

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
    
                $entrevista = [
                    'Fecha Entrevista' => $entry->fecha_entrevista,
                    'Estado (Entrevista)' => $entry->estado?->nombre,
                    'Observaciones' => $entry->observaciones,
                ];
    
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
                    'Evaluacion' => $entry->evaluacion?->nombre,
                    'Adición' => $entry->adicion,
                    'Continúa' => $entry->continua ? '✅' : '❌',
                    'Observaciones Contrato' => $entry->observaciones_contrato,
                    'Tiempo Ejecución (días)' => $entry->tiempo_ejecucion_dias,
                    'Valor Total' => $entry->valor_total,
                    'Fecha Acta Inicio Adic.' => $entry->fecha_acta_inicio_adicion,
                    'Fecha Finalización Adic.' => $entry->fecha_finalizacion_adicion,
                    'Tiempo Ejecución Adic.' => $entry->tiempo_ejecucion_dias_adicion,
                    'Extensión calendario SECOP' => $entry->tiempo_extension_secop_dias !== null ? $entry->tiempo_extension_secop_dias.' días' : null,
                    'Suspensión derivada' => $entry->tiempo_suspension_dias !== null ? $entry->tiempo_suspension_dias.' días' : null,
                    'Total calendario' => $entry->tiempo_total_calendario_dias !== null ? $entry->tiempo_total_calendario_dias.' días' : null,
                    'Valor Adición' => $entry->valor_adicion,
                ];
    
                $html = "<div class='row'>";

                $html .= view()->exists('admin.seguimiento.tracking_context')
                    ? view('admin.seguimiento.tracking_context', compact('entry'))->render()
                    : self::renderFallbackSection('Contexto del seguimiento', [
                        'Nombre' => $entry->persona?->nombre_contratista,
                        'Cédula / NIT' => $entry->persona?->cedula_o_nit,
                        'Dependencia' => $entry->secretaria?->nombre,
                        'Gerencia' => $entry->gerencia?->nombre,
                    ]);
    
                // 🔹 Contrato (mantener en 4 columnas)
                if ($entry->tipo === 'contrato') {
                    $html .= view()->exists('admin.seguimiento.contract_information')
                        ? view('admin.seguimiento.contract_information', compact('entry'))->render()
                        : self::renderFallbackSection('Información del contrato', $contrato);
                    // Las autorizaciones quedan después del resumen contractual para priorizar el estado vigente.
                    $html .= view()->exists('admin.seguimiento.authorization_flow')
                        ? view('admin.seguimiento.authorization_flow', compact('entry'))->render()
                        : self::renderFallbackSection('Autorizaciones', [
                            'Despacho' => $entry->aut_despacho ? 'Sí' : 'No',
                            'Planeación' => $entry->aut_planeacion ? 'Sí' : 'No',
                            'Administrativa' => $entry->aut_administrativa ? 'Sí' : 'No',
                        ]);
                }
    
                // 🔹 Entrevista (mantener en 4 columnas)
                elseif ($entry->tipo === 'entrevista') {
                    $html .= "<div class='col-12'><h5 class='mt-4 text-info'>Información de la Entrevista</h5><div class='row'>";
                    foreach ($entrevista as $label => $valor) {
                        $html .= self::renderCard($label, $valor, 3);
                    }
                    $html .= "</div></div>";
                }
    
                $html .= "</div>";
                return $html;
            }
        ]);

        $this->crud->addColumn([
            'name' => 'control_campos_secop',
            'label' => 'Control de campos SECOP',
            'type' => 'closure',
            'escaped' => false,
            'function' => fn ($entry) => view()->exists('admin.seguimiento.secop_field_control')
                ? view('admin.seguimiento.secop_field_control', compact('entry'))->render()
                : '<div class="alert alert-warning mb-0">El control de campos SECOP no está disponible en este despliegue.</div>',
        ]);

        $this->crud->addColumn([
            'name' => 'historico_aplicaciones_secop',
            'label' => 'Histórico de aplicaciones SECOP',
            'type' => 'closure',
            'escaped' => false,
            'function' => fn ($entry) => view()->exists('admin.seguimiento.secop_history')
                ? view('admin.seguimiento.secop_history', compact('entry'))->render()
                : '<div class="alert alert-warning mb-0">El histórico SECOP no está disponible en este despliegue.</div>',
        ]);
    }

    protected static function renderFallbackSection(string $title, array $values): string
    {
        $html = '<div class="col-12"><h5 class="mt-3 text-primary">'.e($title).'</h5><div class="row">';
        foreach ($values as $label => $value) {
            $html .= self::renderCard(e($label), $value === null || $value === '' ? null : e((string) $value), 3);
        }

        return $html.'</div></div>';
    }
    
    // ✅ Helper actualizado para controlar columnas
    protected static function renderCard($label, $valor, $col = 3)
    {
        return '
            <div class="mb-2 col-md-'.$col.'">
                <div class="border-0 shadow-sm card h-100">
                    <div class="px-3 py-2 card-body">
                        <small class="text-muted">'.$label.'</small>
                        <div class="fw-semibold">'.($valor ?? '<span class="text-muted">N/A</span>').'</div>
                    </div>
                </div>
            </div>';
    }
    public function fetchPersonas(Request $request)
    {
        $term = trim((string) ($request->input('q') ?? $request->input('term') ?? ''));
        $query = Persona::query()->select(['id', 'nombre_contratista', 'cedula_o_nit']);

        if ($term !== '') {
            $query->where(function ($personQuery) use ($term) {
                $personQuery
                    ->where('nombre_contratista', 'like', "%{$term}%")
                    ->orWhere('cedula_o_nit', 'like', "%{$term}%");
            });
        }

        return $query
            ->orderBy('nombre_contratista')
            ->paginate(20)
            ->through(fn (Persona $persona) => [
                'id' => $persona->id,
                'display_name' => trim($persona->nombre_contratista.' — '.($persona->cedula_o_nit ?: 'Sin documento')),
            ]);
    }

    public function exportExcel(Request $request, SeguimientoFilterService $filterService)
    {
        $query = \App\Models\Seguimiento::query()->with('persona');
        $filterService->apply($query, $request->query());

        return Excel::download(new SeguimientoExport($query->get()), 'seguimientos_filtrados.xlsx');
    }



    
}
