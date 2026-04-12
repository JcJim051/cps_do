<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TipoVinculacionRequest;
use App\Models\TipoVinculacion;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class TipoVinculacionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(TipoVinculacion::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/tipo-vinculacion');
        CRUD::setEntityNameStrings('tipo de vinculación', 'tipos de vinculación');
    }

    protected function setupListOperation(): void
    {
        CRUD::column('nombre')->label('Nombre');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(TipoVinculacionRequest::class);
        CRUD::field('nombre')->label('Nombre');
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
