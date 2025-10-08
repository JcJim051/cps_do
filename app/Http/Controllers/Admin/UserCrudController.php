<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\Referencia;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Spatie\Permission\Models\Role;

class UserCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('usuario', 'usuarios');
    }

    protected function setupListOperation(): void
    {
        CRUD::column('name')->label('Nombre');
        CRUD::column('email')->label('Correo');
        CRUD::column('role_id')->label('Rol');
        CRUD::column('referencia_id')->label('Referencia')->type('select')
            ->entity('referencia')
            ->model(Referencia::class)
            ->attribute('nombre');
            CRUD::addColumn([
                'name'      => 'secretaria_id',
                'label'     => 'Secretaría',
                'type'      => 'select',
                'entity'    => 'secretaria',
                'attribute' => 'nombre',
                'model'     => \App\Models\Secretaria::class,
                'wrapper'   => ['style' => 'font-size:13px; white-space:normal;'],
            ]);
        
            // Mostrar Gerencia
            CRUD::addColumn([
                'name'      => 'gerencia_id',
                'label'     => 'Gerencia',
                'type'      => 'select',
                'entity'    => 'gerencia',
                'attribute' => 'nombre',
                'model'     => \App\Models\Gerencia::class,
                'wrapper'   => ['style' => 'font-size:13px; white-space:normal;'],
            ]);
        
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(UserRequest::class);

        CRUD::field('name')->label('Nombre');
        CRUD::field('email')->label('Correo electrónico');

        // Checklist de roles
        CRUD::addField([
            'name' => 'role_id', // ahora usamos directamente el campo role que apunta al id
            'label' => 'Rol',
            'type' => 'select',
            'entity' => 'roleRelation', // nombre de la relación en el modelo
            'model' => \Spatie\Permission\Models\Role::class,
            'attribute' => 'name', // columna que se muestra
            'allows_null' => false,
        ]);

        // Referencia
        CRUD::addField([
            'name' => 'referencia_id',
            'label' => 'Referencia',
            'type' => 'select',
            'entity' => 'referencia',
            'model' => Referencia::class,
            'attribute' => 'nombre',
            'allows_null' => true,
        ]);

        CRUD::addField([
            'name' => 'secretaria_id',
            'label' => 'Secretaría',
            'type' => 'select',
            'entity' => 'secretaria',
            'attribute' => 'nombre',
            'model' => \App\Models\Secretaria::class,
            'wrapper' => ['class' => 'form-group col-md-6'],
            'allows_null' => true,
        ]);
    
        CRUD::addField([
            'name' => 'gerencia_id',
            'label' => 'Gerencia',
            'type' => 'select',
            'entity' => 'gerencia',
            'attribute' => 'nombre',
            'model' => \App\Models\Gerencia::class,
            'wrapper' => ['class' => 'form-group col-md-6'],
            'allows_null' => true,
        ]);
    
     
    
        // --- JS para filtrar gerencias según secretaria ---
        CRUD::addField([
            'name'  => 'filtrado_js',
            'type'  => 'custom_html',
            'value' => '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const secretaria = document.querySelector("[name=secretaria_id]");
                    const gerencia   = document.querySelector("[name=gerencia_id]");
    
                    function cargarGerencias(secretariaId) {
                        if (!secretariaId) return;
                        fetch("/admin/gerencias-por-secretaria/" + secretariaId)
                            .then(res => res.json())
                            .then(data => {
                                gerencia.innerHTML = "";
                                data.forEach(item => {
                                    const option = document.createElement("option");
                                    option.value = item.id;
                                    option.text  = item.text;
                                    gerencia.appendChild(option);
                                });
                            });
                    }
    
                    secretaria?.addEventListener("change", function() {
                        cargarGerencias(this.value);
                    });
    
                    // Si ya hay valor al cargar (edición)
                    if (secretaria?.value) {
                        cargarGerencias(secretaria.value);
                    }
                });
            </script>',
        ]);
       
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

      
}
