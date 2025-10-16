<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\GerenciaRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class GerenciaCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configuración básica
     */
    public function setup(): void
    {
        CRUD::setModel(\App\Models\Gerencia::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/gerencia');
        CRUD::setEntityNameStrings('gerencia', 'gerencias');
    }

    /**
     * LISTA → mostramos solo la convención
     */
    protected function setupListOperation(): void
    {

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID'
        ]);
      
        CRUD::column('convencion')->label('Sigla');

        CRUD::column('secretaria_id')
            ->type('select')
            ->entity('secretaria')
            ->attribute('convencion') // Mostrar sigla de la Secretaría
            ->model(\App\Models\Secretaria::class)
            ->label('Secretaría');
    }

    /**
     * SHOW → mostramos nombre completo y sigla
     */
    protected function setupShowOperation(): void
    {
        CRUD::column('nombre')->label('Nombre completo');
        CRUD::column('convencion')->label('Sigla');

        CRUD::column('secretaria_id')
            ->type('select')
            ->entity('secretaria')
            ->attribute('nombre') // Mostrar nombre completo de la Secretaría
            ->model(\App\Models\Secretaria::class)
            ->label('Secretaría');
    }

    /**
     * CREATE → formulario de creación
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(GerenciaRequest::class);

        CRUD::field('secretaria_id')
            ->type('select')
            ->entity('secretaria')
            ->model(\App\Models\Secretaria::class)
            ->attribute('convencion')
            ->label('Secretaría');

        CRUD::field('nombre')->label('Nombre completo')->type('text');
        CRUD::field('convencion')->label('Sigla')->type('text');
    }

    /**
     * UPDATE → reutiliza el formulario de CREATE
     */
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
