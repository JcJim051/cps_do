<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SeguimientoNomRequest;
use App\Imports\SeguimientoNomImport;
use App\Exports\SeguimientoNomTemplateExport;
use App\Exports\SeguimientoNomExport;
use App\Models\SeguimientoNom;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Alert;

class SeguimientoNomCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(SeguimientoNom::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/seguimiento-nom');
        CRUD::setEntityNameStrings('seguimiento nom', 'seguimientos nom');
    }

    protected function setupListOperation(): void
    {
        $this->crud->addButtonFromView('top', 'import', 'import_seguimientos_button', 'end');
        $this->crud->addButtonFromView('top', 'export_excel', 'buttons.export_excel', 'end');
        $this->crud->enableExportButtons();

        $this->crud->addColumn([
            'name' => 'persona',
            'label' => 'Persona',
            'type' => 'relationship',
            'entity' => 'persona',
            'model' => 'App\\Models\\Persona',
            'attribute' => 'nombre_contratista',
        ]);
        $this->crud->addColumn([
            'name' => 'secretaria',
            'label' => 'Dependencia',
            'type' => 'relationship',
            'entity' => 'secretaria',
            'model' => 'App\\Models\\Secretaria',
            'attribute' => 'nombre',
        ]);
        $this->crud->addColumn([
            'name' => 'cargo',
            'label' => 'Cargo',
            'type' => 'relationship',
            'entity' => 'cargo',
            'model' => 'App\\Models\\Cargo',
            'attribute' => 'nombre',
        ]);
        $this->crud->addColumn([
            'name' => 'tipoVinculacion',
            'label' => 'Tipo Vinculación',
            'type' => 'relationship',
            'entity' => 'tipoVinculacion',
            'model' => 'App\\Models\\TipoVinculacion',
            'attribute' => 'nombre',
        ]);
        $this->crud->addColumn([
            'name' => 'fecha_ingreso',
            'label' => 'Fecha Ingreso',
            'type' => 'date',
        ]);
        $this->crud->addColumn([
            'name' => 'salario',
            'label' => 'Salario',
            'type' => 'number',
            'prefix' => '$ ',
            'decimals' => 2,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SeguimientoNomRequest::class);

        CRUD::addField([
            'name' => 'persona_id',
            'label' => 'Persona',
            'type' => 'select2',
            'entity' => 'persona',
            'model' => 'App\\Models\\Persona',
            'attribute' => 'nombre_contratista',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'secretaria_id',
            'label' => 'Dependencia',
            'type' => 'select2',
            'entity' => 'secretaria',
            'model' => 'App\\Models\\Secretaria',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name' => 'cargo_id',
            'label' => 'Cargo',
            'type' => 'select2',
            'entity' => 'cargo',
            'model' => 'App\\Models\\Cargo',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'tipo_vinculacion_id',
            'label' => 'Tipo de Vinculación',
            'type' => 'select2',
            'entity' => 'tipoVinculacion',
            'model' => 'App\\Models\\TipoVinculacion',
            'attribute' => 'nombre',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'fecha_ingreso',
            'label' => 'Fecha de ingreso',
            'type' => 'date',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
        CRUD::addField([
            'name' => 'salario',
            'label' => 'Salario',
            'type' => 'number',
            'prefix' => '$ ',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->crud->set('show.setFromDb', false);
        $this->crud->set('show.contentClass', 'container-fluid');

        $this->crud->addColumn([
            'name'     => 'detalle_seguimiento_nom',
            'label'    => 'Detalle del Seguimiento Nom',
            'type'     => 'closure',
            'escaped'  => false,
            'function' => function ($entry) {
                $persona = $entry->persona;

                $personaCampos = [
                    'Nombre' => $persona?->nombre_contratista,
                    'Cédula/NIT' => $persona?->cedula_o_nit,
                    'Celular' => $persona?->celular,
                ];

                $salario = $entry->salario !== null
                    ? '$ ' . number_format((float) $entry->salario, 0, ',', '.')
                    : null;

                $fechaIngreso = null;
                if (!empty($entry->fecha_ingreso)) {
                    try {
                        $fechaIngreso = \Carbon\Carbon::parse($entry->fecha_ingreso)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $fechaIngreso = $entry->fecha_ingreso;
                    }
                }

                $generales = [
                    'Dependencia' => $entry->secretaria?->nombre,
                    'Cargo' => $entry->cargo?->nombre,
                    'Tipo Vinculación' => $entry->tipoVinculacion?->nombre,
                    'Fecha Ingreso' => $fechaIngreso,
                    'Salario' => $salario,
                ];

                $html = "<div class='row'>";

                $html .= "<div class='col-12'><h5 class='mt-3 text-primary'>Datos de la Persona</h5><div class='row'>";
                foreach ($personaCampos as $label => $valor) {
                    $html .= self::renderCard($label, $valor, 4);
                }
                $html .= "</div></div>";

                $html .= "<div class='col-12'><h5 class='mt-3 text-primary'>Datos Generales</h5><div class='row'>";
                foreach ($generales as $label => $valor) {
                    $html .= self::renderCard($label, $valor, 4);
                }
                $html .= "</div></div>";

                $html .= "</div>";
                return $html;
            }
        ]);
    }

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

    public function importForm()
    {
        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Importar Seguimientos Nom';
        $this->data['ruta_post'] = url($this->crud->route . '/import');
        $this->data['importFailures'] = session('importFailures', []);
        return view('admin.seguimiento_nom.import', $this->data);
    }

    public function import(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv',
        ], [
            'file.required' => 'Debe seleccionar un archivo.',
            'file.mimes' => 'El archivo debe ser de tipo Excel (.xls, .xlsx) o CSV.',
        ]);

        $import = new SeguimientoNomImport;

        try {
            Excel::import($import, $request->file('file'));
            $failures = $import->logicFailures;

            if (empty($failures)) {
                Alert::success('¡Importación de Seguimientos Nom completada exitosamente!')->flash();
                return redirect($this->crud->route);
            }

            $totalFailures = count($failures);
            Alert::warning("Importación finalizada con {$totalFailures} fila(s) no importada(s). Revise el listado a continuación.")->flash();
            return redirect($this->crud->route . '/import')->with('importFailures', $failures);
        } catch (\Throwable $e) {
            Log::error('Error fatal en la importación de Seguimientos Nom: ' . $e->getMessage());
            Alert::error('Ocurrió un error grave en el servidor. Revise el log de Laravel: ' . $e->getMessage())->flash();
            return back();
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new SeguimientoNomTemplateExport, 'plantilla_seguimientos_nom.xlsx');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->crud->model->newQuery();
        $filters = $request->all();

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;

            switch ($key) {
                case 'persona_id':
                case 'secretaria_id':
                case 'cargo_id':
                case 'tipo_vinculacion_id':
                    $query->where($key, $value);
                    break;
            }
        }

        $entries = $query->get();
        return Excel::download(new SeguimientoNomExport($entries), 'seguimientos_nom_filtrados.xlsx');
    }
}
