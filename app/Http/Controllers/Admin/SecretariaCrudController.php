<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SecretariaRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SecretariaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SecretariaCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Secretaria::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/secretaria');
        CRUD::setEntityNameStrings('secretaria', 'secretarias');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID'
        ]);
        
        CRUD::addColumn([
            'name' => 'nombre', // o el campo real en tu tabla
            'label' => 'Nombre'
        ]);
        CRUD::addColumn(['name' => 'nit_secop', 'label' => 'NIT SECOP']);
        CRUD::addColumn(['name' => 'nombre_secop', 'label' => 'Entidad en SECOP']);
        CRUD::addColumn([
            'name' => 'auditoria_secop_incluida',
            'label' => 'Auditoría departamental',
            'type' => 'boolean',
            'options' => [0 => 'No', 1 => 'Sí'],
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SecretariaRequest::class);
        CRUD::addField(['name' => 'nombre', 'label' => 'Nombre']);
        CRUD::addField(['name' => 'convencion', 'label' => 'Convención']);
        CRUD::addField([
            'name' => 'nit_secop',
            'label' => 'NIT de la entidad contratante en SECOP',
            'hint' => 'Puede escribirse con o sin dígito de verificación; Integra consultará ambas variantes.',
        ]);
        CRUD::addField([
            'name' => 'nombre_secop',
            'label' => 'Nombre de la entidad en SECOP',
            'hint' => 'Ejemplo: DEPARTAMENTO DEL META.',
        ]);
        CRUD::addField([
            'name' => 'auditoria_secop_incluida',
            'label' => 'Incluir en auditoría SECOP departamental',
            'type' => 'checkbox',
            'hint' => 'Actívalo únicamente para el Departamento del Meta y sus entidades descentralizadas.',
        ]);

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
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
