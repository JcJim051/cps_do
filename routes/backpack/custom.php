<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes


    Route::group(['middleware' => ['role:admin,diana']], function () {
        Route::crud('referencia', 'ReferenciaCrudController');
        Route::crud('fuente', 'FuenteCrudController');
        Route::crud('evaluacion', 'EvaluacionCrudController');
        Route::crud('nivel-academico', 'NivelAcademicoCrudController');
        Route::crud('estados', 'EstadosCrudController');
        Route::crud('secretaria', 'SecretariaCrudController');
        Route::crud('gerencia', 'GerenciaCrudController');
        Route::crud('caso', 'CasoCrudController');
        Route::crud('user', 'UserCrudController');
        Route::crud('estado-persona', 'EstadoPersonaCrudController');
        Route::crud('tipo', 'TipoCrudController');
        Route::crud('role', 'RoleCrudController');
        Route::crud('permission', 'PermissionCrudController');
        Route::get('indicadores', [\App\Http\Controllers\Admin\IndicadoresController::class, 'index'])->name('admin.indicadores');

    });


   
    Route::crud('persona', 'PersonaCrudController');
    Route::crud('seguimiento', 'SeguimientoCrudController');
    Route::get('person/import', 'App\Http\Controllers\Admin\PersonaCrudController@importForm')->name('person.importForm');
    Route::post('person/import', 'App\Http\Controllers\Admin\PersonaCrudController@import')->name('person.import');
    Route::get('person/template', 'App\Http\Controllers\Admin\PersonaCrudController@downloadTemplate')->name('person.downloadTemplate');
    Route::get('seguimiento/import', 'App\Http\Controllers\Admin\SeguimientoCrudController@importForm')->name('seguimiento.importForm');
    Route::post('seguimiento/import', 'App\Http\Controllers\Admin\SeguimientoCrudController@import')->name('seguimiento.import');
    Route::get('seguimiento/template', 'App\Http\Controllers\Admin\SeguimientoCrudController@downloadTemplate')->name('seguimiento.downloadTemplate');
    
    
    Route::crud('autorizacion', 'AutorizacionCrudController');
   
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
