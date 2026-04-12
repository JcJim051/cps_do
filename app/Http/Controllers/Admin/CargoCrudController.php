<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CargoRequest;
use App\Models\Cargo;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class CargoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Cargo::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/cargo');
        CRUD::setEntityNameStrings('cargo', 'cargos');
    }

    protected function setupListOperation(): void
    {
        CRUD::column('nombre')->label('Nombre');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(CargoRequest::class);
        CRUD::field('nombre')->label('Nombre');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
