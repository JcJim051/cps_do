<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AutorizacionRequest;
use App\Models\Autorizacion;
use App\Models\Gerencia;
use App\Models\Persona;
use App\Models\Secretaria;
use App\Models\Seguimiento;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutorizacionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Autorizacion::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/autorizacion');
        CRUD::setEntityNameStrings('Autorizacion', 'Autorizaciones');

        $this->middleware(['role:administrativa,bancos,diana,admin']);

        $user = backpack_user();
        if ($user && (int) $user->role_id === 8) {
            abort(403, 'No tienes permisos para acceder a este modulo');
        }
        if ($user && $user->hasAnyRole(['coordinador', 'coordinador_comite'])) {
            abort(403, 'No tienes permisos para acceder a este modulo');
        }
    }

    protected function setupListOperation(): void
    {
        $this->crud->query = $this->crud->query
            ->from('seguimientos')
            ->crossJoin(DB::raw("(SELECT 'inicial' as fase_listado UNION ALL SELECT 'adicion' as fase_listado) as fases"))
            ->where('seguimientos.tipo', 'contrato')
            ->where(function ($q) {
                $q->where('fases.fase_listado', 'inicial')
                    ->orWhere(function ($q2) {
                        $q2->where('fases.fase_listado', 'adicion')
                            ->where('seguimientos.adicion', 'SI');
                    });
            })
            ->select('seguimientos.*', DB::raw('fases.fase_listado as fase_listado'));

        $this->crud->addClause('with', ['persona', 'secretaria', 'gerencia', 'fuente']);
        $this->crud->enableExportButtons();

        $this->crud->addFilter([
            'name'  => 'fase_listado',
            'type'  => 'dropdown',
            'label' => 'Fase',
        ], [
            'inicial' => 'Inicial',
            'adicion' => 'Adicion',
        ], function ($value) {
            $this->crud->addClause('where', 'fases.fase_listado', $value);
        });

        $this->crud->addFilter([
            'name'  => 'anio',
            'type'  => 'select2',
            'label' => 'Ano',
        ], function () {
            return Seguimiento::select('anio')->distinct()->orderBy('anio', 'desc')->pluck('anio', 'anio')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'seguimientos.anio', $value);
        });

        $this->crud->addFilter([
            'name'  => 'secretaria_id',
            'type'  => 'select2',
            'label' => 'Secretaria',
        ], function () {
            return Secretaria::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'seguimientos.secretaria_id', $value);
        });

        $this->crud->addFilter([
            'name'  => 'gerencia_id',
            'type'  => 'select2',
            'label' => 'Gerencia',
        ], function () {
            return Gerencia::pluck('nombre', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'seguimientos.gerencia_id', $value);
        });

        $this->crud->addFilter([
            'name'  => 'persona_id',
            'type'  => 'select2_ajax',
            'label' => 'Persona',
            'placeholder' => 'Buscar persona...',
            'minimum_input_length' => 2,
            'select_attribute' => 'display_name',
            'select_key' => 'id',
        ], backpack_url('autorizacion/fetch-persona'), function ($value) {
            $this->crud->addClause('where', 'seguimientos.persona_id', $value);
        });

        $this->crud->addFilter([
            'name'  => 'aut_despacho',
            'type'  => 'dropdown',
            'label' => 'Aut 1',
        ], [1 => 'Autorizado', 0 => 'No Autorizado'], function ($value) {
            $this->crud->query->whereRaw(
                "(CASE WHEN fases.fase_listado='adicion' THEN seguimientos.aut_despacho_adicion ELSE seguimientos.aut_despacho END) = ?",
                [(int) $value]
            );
        });

        $this->crud->addFilter([
            'name'  => 'aut_planeacion',
            'type'  => 'dropdown',
            'label' => 'Aut 2',
        ], [1 => 'Autorizado', 0 => 'No Autorizado'], function ($value) {
            $this->crud->query->whereRaw(
                "(CASE WHEN fases.fase_listado='adicion' THEN seguimientos.aut_planeacion_adicion ELSE seguimientos.aut_planeacion END) = ?",
                [(int) $value]
            );
        });

        $this->crud->addFilter([
            'name'  => 'aut_administrativa',
            'type'  => 'dropdown',
            'label' => 'Aut 3',
        ], [1 => 'Autorizado', 0 => 'No Autorizado'], function ($value) {
            $this->crud->query->whereRaw(
                "(CASE WHEN fases.fase_listado='adicion' THEN seguimientos.aut_administrativa_adicion ELSE seguimientos.aut_administrativa END) = ?",
                [(int) $value]
            );
        });

        CRUD::addColumn([
            'name' => 'fase_listado',
            'label' => 'Fase',
            'type' => 'closure',
            'escaped' => false,
            'function' => function ($entry) {
                $isAdicion = ($entry->fase_listado ?? 'inicial') === 'adicion';
                $label = $isAdicion ? 'Adicion' : 'Inicial';
                $class = $isAdicion ? 'bg-info' : 'bg-primary';
                return '<span class="badge '.$class.'">'.$label.'</span>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'secretaria_id',
            'label' => 'Secretaria',
            'type' => 'select',
            'entity' => 'secretaria',
            'model' => Secretaria::class,
            'attribute' => 'convencion',
        ]);

        CRUD::addColumn([
            'name' => 'persona_id',
            'label' => 'Nombre',
            'type' => 'closure',
            'escaped' => false,
            'function' => function ($entry) {
                return e(optional($entry->persona)->nombre_contratista ?? 'N/A');
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('persona', function ($q) use ($searchTerm) {
                    $q->where('nombre_contratista', 'like', '%'.$searchTerm.'%');
                });
            },
        ]);

        CRUD::addColumn([
            'name' => 'valor_fase',
            'label' => 'Valor',
            'type' => 'closure',
            'function' => function ($entry) {
                $isAdicion = ($entry->fase_listado ?? 'inicial') === 'adicion';
                $value = $isAdicion ? ($entry->valor_adicion ?? 0) : ($entry->valor_total ?? 0);
                return '$'.number_format((float) $value, 2, ',', '.');
            },
        ]);

        CRUD::addColumn([
            'name' => 'aut_despacho',
            'label' => 'Aut 1',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.columns.autorizacion_toggle',
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'aut_planeacion',
            'label' => 'Aut 2',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.columns.autorizacion_toggle',
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'aut_administrativa',
            'label' => 'Aut 3',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.columns.autorizacion_toggle',
            'escaped' => false,
        ]);

        CRUD::addColumn([
            'name' => 'estado_aprobacion',
            'label' => 'Estado',
            'type' => 'view',
            'view' => 'vendor.backpack.crud.columns.estado_aprobacion_select',
            'escaped' => false,
        ]);

        $this->crud->removeButtons(['create', 'show', 'delete', 'update']);
        $this->crud->addButtonFromView('line', 'update', 'update_new_tab');
    }

    protected function generateAuthorizationCards($entry): string
    {
        $campos = [
            'Nombre Contratista' => $entry->persona->nombre_contratista ?? 'N/A',
            'Cedula/NIT' => $entry->persona->cedula_o_nit ?? 'N/A',
            'Secretaria' => $entry->secretaria->nombre ?? 'N/A',
            'Gerencia' => $entry->gerencia->nombre ?? 'N/A',
            'Fuente Financiacion' => $entry->fuente->nombre ?? 'N/A',
            'Valor Mensual' => '$'.number_format((float) ($entry->valor_mensual ?? 0), 2, ',', '.'),
            'Fecha Acta de Inicio' => $entry->fecha_acta_inicio ?? 'N/A',
            'Fecha Finalizacion' => $entry->fecha_finalizacion ?? 'N/A',
            'Tiempo Ejecucion (dias)' => $entry->tiempo_ejecucion_dias ?? 'N/A',
            'Valor Total' => '$'.number_format((float) ($entry->valor_total ?? 0), 2, ',', '.'),
            'Tiene Adicion' => ($entry->adicion ?? null) === 'SI' ? 'SI' : 'NO',
            'Valor Total Contrato' => '$'.number_format((float) ($entry->valor_total_contrato ?? 0), 2, ',', '.'),
        ];

        $html = '<div class="pt-3 row">';
        foreach ($campos as $label => $valor) {
            $isResumenClave = in_array($label, ['Tiene Adicion', 'Valor Total Contrato'], true);
            $cardClass = $isResumenClave ? 'border-info-subtle bg-info-subtle' : 'border-0';
            $labelClass = $isResumenClave ? 'text-info-emphasis' : 'text-muted';
            $html .= '
                <div class="mb-3 col-6 col-md-3">
                    <div class="shadow-sm card h-100 '.$cardClass.'">
                        <div class="px-3 py-2 card-body">
                            <small class="'.$labelClass.' text-uppercase fw-normal d-block text-truncate" title="'.$label.'">'.$label.'</small>
                            <div class="fw-bold fs-6">'.$valor.'</div>
                        </div>
                    </div>
                </div>';
        }
        $html .= '</div>';

        return $html;
    }

    protected function renderAdicionInfoCards($entry): string
    {
        $camposAdicion = [
            'Fecha Inicio Adicion' => $entry->fecha_acta_inicio_adicion ?? 'N/A',
            'Fecha Fin Adicion' => $entry->fecha_finalizacion_adicion ?? 'N/A',
            'Tiempo Adicion (dias)' => $entry->tiempo_ejecucion_dias_adicion ?? 'N/A',
            'Valor Adicion' => '$'.number_format((float) ($entry->valor_adicion ?? 0), 2, ',', '.'),
        ];

        $html = '<div class="pt-1 pb-2 row">';
        foreach ($camposAdicion as $label => $valor) {
            $html .= '
                <div class="mb-2 col-6 col-md-3">
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
        $requestedPhase = request('fase') === 'adicion' ? 'adicion' : 'inicial';
        $canEditDespacho = backpack_user()->hasAnyRole(['diana', 'admin']);
        $canEditPlaneacion = backpack_user()->hasAnyRole(['bancos', 'diana', 'admin']);
        $canEditAdministrativa = backpack_user()->hasAnyRole(['administrativa', 'diana', 'admin']);
        $canEditEstadoAprobacion = backpack_user()->hasRole('bancos');
        $canViewEstadoAprobacion = backpack_user()->hasAnyRole(['bancos', 'diana', 'admin']);
        $canEditInitialBlock = $requestedPhase !== 'adicion';

        CRUD::addField([
            'name' => 'datos_autorizacion_read_only',
            'label' => 'Informacion del Contrato',
            'type' => 'custom_html',
            'value' => $this->generateAuthorizationCards($entry),
            'wrapper' => ['class' => 'form-group col-12'],
        ]);

        CRUD::addField([
            'name' => 'title_aut_inicial',
            'type' => 'custom_html',
            'value' => '<div id="bloque-autorizaciones-inicial" class="mt-2 mb-2"><h5 class="text-primary">Autorizaciones Iniciales</h5></div>',
            'wrapper' => ['class' => 'form-group col-12'],
        ]);

        $this->addAuthorizationPhaseFields(
            '',
            'inicial',
            $canEditInitialBlock ? $canEditDespacho : false,
            $canEditInitialBlock ? $canEditPlaneacion : false,
            $canEditInitialBlock ? $canEditAdministrativa : false,
            $entry
        );

        if ($canEditEstadoAprobacion && $canEditInitialBlock) {
            CRUD::addField([
                'name'  => 'estado_aprobacion',
                'label' => 'Estado de Aprobacion',
                'type'  => 'select2_from_array',
                'options' => [
                    'mayor'  => 'Mayor valor al aprobado.',
                    'menor'  => 'Menor valor al aprobado.',
                    'sin'    => 'Sin aprobacion',
                ],
                'allows_null' => true,
                'wrapper' => ['class' => 'form-group col-md-4'],
            ]);
        } elseif ($canViewEstadoAprobacion) {
            CRUD::addField([
                'name' => 'estado_aprobacion_readonly',
                'label' => 'Estado de Aprobacion',
                'type' => 'custom_html',
                'value' => '<div class="form-control bg-light">'.e($entry?->getEstadoAprobacionShort() ?: 'Sin estado').'</div>',
                'wrapper' => ['class' => 'form-group col-md-4'],
            ]);
        }

        $hasAdicion = $entry && $entry->adicion === 'SI';
        $canEditAdicionBlock = $requestedPhase === 'adicion';

        CRUD::addField([
            'name' => 'title_aut_adicion',
            'type' => 'custom_html',
            'value' => '<div id="bloque-autorizaciones-adicion" class="mt-3 mb-2"><h5 class="text-info">Autorizaciones Adicion</h5></div>',
            'wrapper' => ['class' => 'form-group col-12'],
        ]);

        if ($hasAdicion) {
            CRUD::addField([
                'name' => 'datos_adicion_read_only',
                'type' => 'custom_html',
                'value' => $this->renderAdicionInfoCards($entry),
                'wrapper' => ['class' => 'form-group col-12'],
            ]);
            $this->addAuthorizationPhaseFields(
                '_adicion',
                'adicion',
                $canEditAdicionBlock ? $canEditDespacho : false,
                $canEditAdicionBlock ? $canEditPlaneacion : false,
                $canEditAdicionBlock ? $canEditAdministrativa : false,
                $entry
            );

            if ($canEditEstadoAprobacion && $canEditAdicionBlock) {
                CRUD::addField([
                    'name'  => 'estado_aprobacion_adicion',
                    'label' => 'Estado de Aprobacion (Adicion)',
                    'type'  => 'select2_from_array',
                    'options' => [
                        'mayor'  => 'Mayor valor al aprobado.',
                        'menor'  => 'Menor valor al aprobado.',
                        'sin'    => 'Sin aprobacion',
                    ],
                    'allows_null' => true,
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ]);
            } elseif ($canViewEstadoAprobacion) {
                CRUD::addField([
                    'name' => 'estado_aprobacion_adicion_readonly',
                    'label' => 'Estado de Aprobacion (Adicion)',
                    'type' => 'custom_html',
                    'value' => '<div class="form-control bg-light">'.e($entry?->getEstadoAprobacionAdicionShort() ?: 'Sin estado').'</div>',
                    'wrapper' => ['class' => 'form-group col-md-4'],
                ]);
            }
        } else {
            CRUD::addField([
                'name' => 'autorizacion_adicion_no_aplica',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-light border">No aplica: este contrato no tiene adicion activa.</div>',
                'wrapper' => ['class' => 'form-group col-12'],
            ]);
        }

        CRUD::addField([
            'name' => 'script_autorizaciones',
            'type' => 'custom_html',
            'value' => '
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    function bindFecha(autoName, fechaName) {
                        var checkbox = document.querySelector("[name=\'" + autoName + "\']");
                        var fechaInput = document.querySelector("[name=\'" + fechaName + "\']");
                        if (!checkbox || !fechaInput) {
                            return;
                        }
                        checkbox.addEventListener("change", function() {
                            if (this.checked && !fechaInput.value) {
                                fechaInput.value = new Date().toISOString().split("T")[0];
                            }
                        });
                    }

                    bindFecha("aut_despacho", "fecha_aut_despacho");
                    bindFecha("aut_planeacion", "fecha_aut_planeacion");
                    bindFecha("aut_administrativa", "fecha_aut_administrativa");
                    bindFecha("aut_despacho_adicion", "fecha_aut_despacho_adicion");
                    bindFecha("aut_planeacion_adicion", "fecha_aut_planeacion_adicion");
                    bindFecha("aut_administrativa_adicion", "fecha_aut_administrativa_adicion");

                    var fase = "'.e((string) request('fase', '')).'";
                    if (fase === "adicion") {
                        var target = document.getElementById("bloque-autorizaciones-adicion");
                        if (target) {
                            target.scrollIntoView({ behavior: "smooth", block: "start" });
                        }
                    }
                });
                </script>
            ',
            'wrapper' => ['class' => 'form-group col-12'],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->crud->set('show.setFromDb', false);

        $this->crud->addColumn([
            'name' => 'autorizaciones_show',
            'label' => 'Autorizaciones',
            'type' => 'closure',
            'escaped' => false,
            'function' => function ($entry) {
                $map = fn ($v) => $v ? 'Aprobado' : 'Pendiente';

                $html = '<div class="row">';
                $html .= '<div class="col-md-6"><div class="card"><div class="card-header bg-primary text-white">Inicial</div><div class="card-body">';
                $html .= '<p>Aut 1: '.$map((bool) $entry->aut_despacho).'</p>';
                $html .= '<p>Aut 2: '.$map((bool) $entry->aut_planeacion).'</p>';
                $html .= '<p>Aut 3: '.$map((bool) $entry->aut_administrativa).'</p>';
                $html .= '</div></div></div>';

                if ($entry->adicion === 'SI') {
                    $html .= '<div class="col-md-6"><div class="card"><div class="card-header bg-info text-white">Adicion</div><div class="card-body">';
                    $html .= '<p>Aut 1: '.$map((bool) $entry->aut_despacho_adicion).'</p>';
                    $html .= '<p>Aut 2: '.$map((bool) $entry->aut_planeacion_adicion).'</p>';
                    $html .= '<p>Aut 3: '.$map((bool) $entry->aut_administrativa_adicion).'</p>';
                    $html .= '</div></div></div>';
                }

                $html .= '</div>';
                return $html;
            },
        ]);
    }

    protected function addAuthorizationPhaseFields(
        string $suffix,
        string $phaseLabel,
        bool $canEditDespacho,
        bool $canEditPlaneacion,
        bool $canEditAdministrativa,
        $entry
    ): void {
        $prefixLabel = ucfirst($phaseLabel);

        $this->addAuthorizationField('aut_despacho'.$suffix, 'Autorizacion 1 ('.$prefixLabel.')', $canEditDespacho, $entry, 'aut_despacho'.$suffix, 'col-md-2');
        $this->addDateReadonlyField('fecha_aut_despacho'.$suffix, 'col-md-2');

        $this->addAuthorizationField('aut_planeacion'.$suffix, 'Autorizacion 2 ('.$prefixLabel.')', $canEditPlaneacion, $entry, 'aut_planeacion'.$suffix, 'col-md-2');
        $this->addDateReadonlyField('fecha_aut_planeacion'.$suffix, 'col-md-2');

        $this->addAuthorizationField('aut_administrativa'.$suffix, 'Autorizacion 3 ('.$prefixLabel.')', $canEditAdministrativa, $entry, 'aut_administrativa'.$suffix, 'col-md-2');
        $this->addDateReadonlyField('fecha_aut_administrativa'.$suffix, 'col-md-2');
    }

    protected function addAuthorizationField(string $name, string $label, bool $canEdit, $entry, string $entryField, string $wrapperClass): void
    {
        if ($canEdit) {
            CRUD::addField([
                'name' => $name,
                'label' => $label,
                'type' => 'switch',
                'default' => 0,
                'wrapper' => ['class' => 'form-group '.$wrapperClass.' text-center'],
            ]);
            return;
        }

        $isChecked = $entry ? (bool) ($entry->{$entryField} ?? false) : false;
        $value = $isChecked
            ? '<div style="color:green; font-size:1.5rem;">✔</div>'
            : '<div style="color:red; font-size:1.5rem;">✖</div>';

        CRUD::addField([
            'name' => $name.'_readonly',
            'label' => $label,
            'type' => 'custom_html',
            'value' => $value,
            'wrapper' => ['class' => 'form-group '.$wrapperClass.' text-center'],
        ]);
    }

    protected function addDateReadonlyField(string $name, string $wrapperClass): void
    {
        CRUD::addField([
            'name' => $name,
            'label' => '',
            'type' => 'date',
            'attributes' => ['readonly' => true, 'style' => 'background-color:#f5f5f5;cursor:not-allowed;'],
            'wrapper' => ['class' => 'form-group '.$wrapperClass],
        ]);
    }

    public function update(Request $request)
    {
        foreach ([
            ['aut_despacho', 'fecha_aut_despacho'],
            ['aut_planeacion', 'fecha_aut_planeacion'],
            ['aut_administrativa', 'fecha_aut_administrativa'],
            ['aut_despacho_adicion', 'fecha_aut_despacho_adicion'],
            ['aut_planeacion_adicion', 'fecha_aut_planeacion_adicion'],
            ['aut_administrativa_adicion', 'fecha_aut_administrativa_adicion'],
        ] as [$auto, $fecha]) {
            if ($request->boolean($auto) && !$request->filled($fecha)) {
                $request->merge([$fecha => now()->format('Y-m-d')]);
            }
        }

        return $this->traitUpdate();
    }

    public function updateEstadoAprobacion(Request $request, $id)
    {
        $user = backpack_user();
        if (!$user || !$user->hasRole('bancos')) {
            abort(403, 'No tienes permisos para actualizar este estado');
        }

        $value = $request->input('value');
        $allowed = ['mayor', 'menor', 'sin', null, ''];
        if (!in_array($value, $allowed, true)) {
            abort(422, 'Estado invalido');
        }

        $fase = $request->input('fase') === 'adicion' ? 'adicion' : 'inicial';
        $targetField = $fase === 'adicion' ? 'estado_aprobacion_adicion' : 'estado_aprobacion';

        $entry = Autorizacion::findOrFail($id);
        $entry->{$targetField} = $value === '' ? null : $value;
        $entry->save();

        return redirect()->back();
    }

    public function fetchPersonaFilter(Request $request)
    {
        $term = $request->input('q');

        $query = Persona::query()->selectRaw("id, CONCAT(nombre_contratista, ' - ', COALESCE(cedula_o_nit,'')) as display_name");

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('nombre_contratista', 'like', "%{$term}%")
                    ->orWhere('cedula_o_nit', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('nombre_contratista')->paginate(15);
    }

    public function togglePlaneacion(Request $request, $id)
    {
        $user = backpack_user();
        if (!$user || !$user->hasAnyRole(['administrativa', 'bancos', 'diana', 'admin'])) {
            abort(403, 'No tienes permisos para actualizar esta autorizacion');
        }

        $entry = Autorizacion::findOrFail($id);
        $value = $request->boolean('value');
        $fase = $request->input('fase') === 'adicion' ? 'adicion' : 'inicial';

        if ($fase === 'adicion') {
            $entry->aut_planeacion_adicion = $value;
            if ($value && empty($entry->fecha_aut_planeacion_adicion)) {
                $entry->fecha_aut_planeacion_adicion = Carbon::now()->toDateString();
            }
        } else {
            $entry->aut_planeacion = $value;
            if ($value && empty($entry->fecha_aut_planeacion)) {
                $entry->fecha_aut_planeacion = Carbon::now()->toDateString();
            }
        }

        $entry->save();

        return redirect()->back();
    }

    public function toggleAutorizacion(Request $request, $id)
    {
        $entry = Autorizacion::findOrFail($id);
        $user = backpack_user();

        $field = (string) $request->input('field');
        $fase = $request->input('fase') === 'adicion' ? 'adicion' : 'inicial';
        $value = $request->boolean('value');

        $allowedFields = ['aut_despacho', 'aut_planeacion', 'aut_administrativa'];
        if (!in_array($field, $allowedFields, true)) {
            abort(422, 'Campo no permitido');
        }

        $canEdit = false;
        if ($field === 'aut_despacho') {
            $canEdit = $user && $user->hasAnyRole(['diana', 'admin']);
        } elseif ($field === 'aut_planeacion') {
            $canEdit = $user && $user->hasAnyRole(['administrativa', 'bancos', 'diana', 'admin']);
        } elseif ($field === 'aut_administrativa') {
            $canEdit = $user && $user->hasAnyRole(['administrativa', 'diana', 'admin']);
        }

        if (!$canEdit) {
            abort(403, 'No tienes permisos para actualizar esta autorizacion');
        }

        $targetField = $fase === 'adicion' ? ($field.'_adicion') : $field;
        $dateFieldMap = [
            'aut_despacho' => 'fecha_aut_despacho',
            'aut_planeacion' => 'fecha_aut_planeacion',
            'aut_administrativa' => 'fecha_aut_administrativa',
        ];
        $targetDate = $fase === 'adicion'
            ? ($dateFieldMap[$field].'_adicion')
            : $dateFieldMap[$field];

        $entry->{$targetField} = $value;
        if ($value && empty($entry->{$targetDate})) {
            $entry->{$targetDate} = Carbon::now()->toDateString();
        }
        $entry->save();

        return redirect()->back();
    }
}
