<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProgramasRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Requests\ProgramaRequest;




/**
 * Class ProgramasCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProgramasCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    


    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Programa::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/programas');
        CRUD::setEntityNameStrings('programa', 'programas');

        $user = backpack_user();

        // 🚫 Bloqueo total por defecto
        $this->crud->denyAccess(['list', 'show', 'create', 'update', 'delete']);

        // ✏️ PROGRAMAS (8) → crear y editar
        if (in_array($user->role_id, [8, 1])) {
            $this->crud->allowAccess(['list', 'show', 'create', 'update', 'delete']);
            $this->crud->denyAccess(['delete']);
            return;
        }

        // 👀 ADMIN (1) y DIANA (2) → SOLO VER
        if (in_array($user->role_id, [2])) {
            $this->crud->allowAccess(['list', 'show']);
            return;
        }

        // 🚫 cualquier otro rol
        abort(403, 'No tienes permisos para acceder a este módulo');
    }


    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation(): void
    {
        /*
        |--------------------------------------------------------------------------
        | EXPORTACIONES
        |--------------------------------------------------------------------------
        */
        $this->crud->enableExportButtons();
    
        /*
        |--------------------------------------------------------------------------
        | COLUMNAS
        |--------------------------------------------------------------------------
        */
        CRUD::addColumn([
            'name'  => 'operador',
            'label' => 'Operador',
        ]);
    
        CRUD::addColumn([
            'name'  => 'municipio',
            'label' => 'Municipio',
        ]);
    
        CRUD::addColumn([
            'name'  => 'institucion',
            'label' => 'Institución',
        ]);
    
        CRUD::addColumn([
            'name'  => 'sede',
            'label' => 'Sede',
        ]);
    
        CRUD::addColumn([
            'name'  => 'nombre_apellidos',
            'label' => 'Nombre y Apellidos',
        ]);
    
        CRUD::addColumn([
            'name'  => 'cedula',
            'label' => 'Cédula',
        ]);
    
        CRUD::addColumn([
            'name'  => 'contacto',
            'label' => 'Contacto',
        ]);
    
        CRUD::addColumn([
            'name'  => 'ref_1',
            'label' => 'Ref 1',
        ]);
    
        CRUD::addColumn([
            'name'  => 'ref_2',
            'label' => 'Ref 2',
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | FILTROS (UNO POR CADA CAMPO)
        |--------------------------------------------------------------------------
        */
    
        // Operador
        CRUD::addFilter([
            'name'  => 'operador',
            'type'  => 'text',
            'label' => 'Operador',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'operador', 'LIKE', "%{$value}%")
        );
    
        // Municipio
        CRUD::addFilter([
            'name'  => 'municipio',
            'type'  => 'select2',
            'label' => 'Municipio',
        ], [
            'Acacías' => 'Acacías',
            'Barranca de Upía' => 'Barranca de Upía',
            'Cabuyaro' => 'Cabuyaro',
            'Castilla la Nueva' => 'Castilla la Nueva',
            'Cubarral' => 'Cubarral',
            'Cumaral' => 'Cumaral',
            'El Calvario' => 'El Calvario',
            'El Castillo' => 'El Castillo',
            'El Dorado' => 'El Dorado',
            'Fuente de Oro' => 'Fuente de Oro',
            'Granada' => 'Granada',
            'Guamal' => 'Guamal',
            'La Macarena' => 'La Macarena',
            'La Uribe' => 'La Uribe',
            'Lejanías' => 'Lejanías',
            'Mapiripán' => 'Mapiripán',
            'Mesetas' => 'Mesetas',
            'Puerto Concordia' => 'Puerto Concordia',
            'Puerto Gaitán' => 'Puerto Gaitán',
            'Puerto Lleras' => 'Puerto Lleras',
            'Puerto López' => 'Puerto López',
            'Puerto Rico' => 'Puerto Rico',
            'Restrepo' => 'Restrepo',
            'San Carlos de Guaroa' => 'San Carlos de Guaroa',
            'San Juan de Arama' => 'San Juan de Arama',
            'San Juanito' => 'San Juanito',
            'San Martín' => 'San Martín',
            'Villavicencio' => 'Villavicencio',
            'Vista Hermosa' => 'Vista Hermosa',
        ], fn ($value) =>
            CRUD::addClause('where', 'municipio', $value)
        );
    
        // Institución
        CRUD::addFilter([
            'name'  => 'institucion',
            'type'  => 'text',
            'label' => 'Institución',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'institucion', 'LIKE', "%{$value}%")
        );
    
        // Sede
        CRUD::addFilter([
            'name'  => 'sede',
            'type'  => 'text',
            'label' => 'Sede',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'sede', 'LIKE', "%{$value}%")
        );
    
        // Nombre y Apellidos
        CRUD::addFilter([
            'name'  => 'nombre_apellidos',
            'type'  => 'text',
            'label' => 'Nombre y Apellidos',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'nombre_apellidos', 'LIKE', "%{$value}%")
        );
    
        // Cédula
        CRUD::addFilter([
            'name'  => 'cedula',
            'type'  => 'text',
            'label' => 'Cédula',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'cedula', 'LIKE', "%{$value}%")
        );
    
        // Contacto
        CRUD::addFilter([
            'name'  => 'contacto',
            'type'  => 'text',
            'label' => 'Contacto',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'contacto', 'LIKE', "%{$value}%")
        );
    
        // Referencia 1
        CRUD::addFilter([
            'name'  => 'ref_1',
            'type'  => 'text',
            'label' => 'Ref 1',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'ref_1', 'LIKE', "%{$value}%")
        );
    
        // Referencia 2
        CRUD::addFilter([
            'name'  => 'ref_2',
            'type'  => 'text',
            'label' => 'Ref 2',
        ], false, fn ($value) =>
            CRUD::addClause('where', 'ref_2', 'LIKE', "%{$value}%")
        );
    }
    


    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(ProgramaRequest::class);
    
        /*
        |--------------------------------------------------------------------------
        | FILA 1: Operador – Municipio – Institución
        |--------------------------------------------------------------------------
        */
    
        CRUD::field('operador')
            ->label('Operador')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::addField([
            'name'    => 'municipio',
            'label'   => 'Municipio',
            'type'    => 'select2_from_array',
            'options' => [
                'Acacías' => 'Acacías',
                'Barranca de Upía' => 'Barranca de Upía',
                'Cabuyaro' => 'Cabuyaro',
                'Castilla la Nueva' => 'Castilla la Nueva',
                'Cubarral' => 'Cubarral',
                'Cumaral' => 'Cumaral',
                'El Calvario' => 'El Calvario',
                'El Castillo' => 'El Castillo',
                'El Dorado' => 'El Dorado',
                'Fuente de Oro' => 'Fuente de Oro',
                'Granada' => 'Granada',
                'Guamal' => 'Guamal',
                'La Macarena' => 'La Macarena',
                'La Uribe' => 'La Uribe',
                'Lejanías' => 'Lejanías',
                'Mapiripán' => 'Mapiripán',
                'Mesetas' => 'Mesetas',
                'Puerto Concordia' => 'Puerto Concordia',
                'Puerto Gaitán' => 'Puerto Gaitán',
                'Puerto Lleras' => 'Puerto Lleras',
                'Puerto López' => 'Puerto López',
                'Puerto Rico' => 'Puerto Rico',
                'Restrepo' => 'Restrepo',
                'San Carlos de Guaroa' => 'San Carlos de Guaroa',
                'San Juan de Arama' => 'San Juan de Arama',
                'San Juanito' => 'San Juanito',
                'San Martín' => 'San Martín',
                'Villavicencio' => 'Villavicencio',
                'Vista Hermosa' => 'Vista Hermosa',
            ],
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
    
        CRUD::field('institucion')
            ->label('Institución')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        /*
        |--------------------------------------------------------------------------
        | FILA 2: Sede – Nombre completo – Cédula
        |--------------------------------------------------------------------------
        */
    
        CRUD::field('sede')
            ->label('Sede')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::field('nombre_apellidos')
            ->label('Nombre y Apellidos')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::field('cedula')
            ->label('Cédula')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        /*
        |--------------------------------------------------------------------------
        | FILA 3: Contacto – Referencia 1 – Referencia 2
        |--------------------------------------------------------------------------
        */
    
        CRUD::field('contacto')
            ->label('Contacto')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::field('ref_1')
            ->label('Referencia 1')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        CRUD::field('ref_2')
            ->label('Referencia 2')
            ->wrapper(['class' => 'form-group col-md-4']);
    
        /*
        |--------------------------------------------------------------------------
        | FILA 4: Foto
        |--------------------------------------------------------------------------
        */
    
        CRUD::addField([
            'name'    => 'foto',
            'label'   => 'Foto',
            'type'    => 'upload',
            'upload'  => true,
            'disk'    => 'public',
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);
    }
    
    protected function setupShowOperation(): void
{
    $this->crud->set('show.setFromDb', false);
    $this->crud->set('show.contentClass', 'container-fluid');

    $this->crud->addColumn([
        'name'     => 'datos_programa',
        'label'    => 'Información del Programa',
        'type'     => 'closure',
        'function' => function ($entry) {

            $html = '<div class="row">';

            /*
            |--------------------------------------------------------------------------
            | COLUMNA 1: FOTO
            |--------------------------------------------------------------------------
            */
            if ($entry->foto) {
                $fotoUrl = asset('storage/' . $entry->foto);

                $html .= '
                    <div class="mb-2 col-md-3 d-flex align-items-stretch">
                        <div class="border-0 shadow-sm card w-100 h-100">
                            <div class="text-center card-body d-flex flex-column justify-content-center">
                                <small class="text-muted">Foto</small>
                                <a href="'.$fotoUrl.'" target="_blank" class="mt-2 d-block">
                                    <img src="'.$fotoUrl.'" class="rounded img-fluid"
                                         style="max-height:250px; object-fit:contain; cursor:pointer;">
                                </a>
                            </div>
                        </div>
                    </div>';
            }

            /*
            |--------------------------------------------------------------------------
            | COLUMNA 2: DATOS (3 columnas internas)
            |--------------------------------------------------------------------------
            */
            $html .= '<div class="col-md-9"><div class="row">';

            $campos = [
                'Operador'            => $entry->operador,
                'Municipio'           => $entry->municipio,
                'Institución'         => $entry->institucion,
                'Sede'                => $entry->sede,
                'Nombre y Apellidos'  => $entry->nombre_apellidos,
                'Cédula'              => $entry->cedula,
                'Contacto'            => $entry->contacto,
                'Referencia 1'        => $entry->ref_1,
                'Referencia 2'        => $entry->ref_2,
            ];

            foreach ($campos as $label => $valor) {
                $html .= '
                    <div class="mb-2 col-md-4">
                        <div class="border-0 shadow-sm card h-100">
                            <div class="px-3 py-2 card-body">
                                <small class="text-muted">'.$label.'</small>
                                <div class="fw-semibold">'.($valor ?: '<span class="text-muted">N/A</span>').'</div>
                            </div>
                        </div>
                    </div>';
            }

            $html .= '</div></div>'; // cierre row interno y col-md-9
            $html .= '</div>'; // cierre row principal

            return $html;
        },
        'escaped' => false,
    ]);
}


    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
