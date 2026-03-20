<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\EjercicioPoliticoRequest;
use App\Models\EjercicioPolitico;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class EjercicioPoliticoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(EjercicioPolitico::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/ejercicio-politico');
        CRUD::setEntityNameStrings('ejercicio político', 'ejercicios políticos');
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
        ]);

        CRUD::addColumn([
            'name' => 'nombre',
            'label' => 'Nombre',
        ]);

        CRUD::addColumn([
            'name' => 'descripcion',
            'label' => 'Descripción',
            'type' => 'text',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(EjercicioPoliticoRequest::class);

        CRUD::field('nombre')->label('Nombre');
        CRUD::field('descripcion')->type('textarea')->label('Descripción');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
