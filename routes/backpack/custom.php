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
        Route::crud('cargo', 'CargoCrudController');
        Route::crud('tipo-vinculacion', 'TipoVinculacionCrudController');
        Route::crud('role', 'RoleCrudController');
        Route::crud('permission', 'PermissionCrudController');
        Route::get('indicadores', [\App\Http\Controllers\Admin\IndicadoresController::class, 'index'])->name('admin.indicadores');
        Route::crud('ejercicio-politico', 'EjercicioPoliticoCrudController');
        Route::get('consulta-datos-abiertos', 'ConsultaDatosAbiertosController@index')->name('consulta-datos-abiertos.index');

    });


   
    Route::crud('persona', 'PersonaCrudController');
    Route::crud('seguimiento', 'SeguimientoCrudController');
    Route::crud('seguimiento-nom', 'SeguimientoNomCrudController');
    Route::get('person/import', 'App\Http\Controllers\Admin\PersonaCrudController@importForm')->name('person.importForm');
    Route::post('person/import', 'App\Http\Controllers\Admin\PersonaCrudController@import')->name('person.import');
    Route::get('person/template', 'App\Http\Controllers\Admin\PersonaCrudController@downloadTemplate')->name('person.downloadTemplate');
    Route::get('seguimiento/import', 'App\Http\Controllers\Admin\SeguimientoCrudController@importForm')->name('seguimiento.importForm');
    Route::post('seguimiento/import', 'App\Http\Controllers\Admin\SeguimientoCrudController@import')->name('seguimiento.import');
    Route::get('seguimiento/template', 'App\Http\Controllers\Admin\SeguimientoCrudController@downloadTemplate')->name('seguimiento.downloadTemplate');
    Route::get('seguimiento-nom/import', 'App\Http\Controllers\Admin\SeguimientoNomCrudController@importForm')->name('seguimiento-nom.importForm');
    Route::post('seguimiento-nom/import', 'App\Http\Controllers\Admin\SeguimientoNomCrudController@import')->name('seguimiento-nom.import');
    Route::get('seguimiento-nom/template', 'App\Http\Controllers\Admin\SeguimientoNomCrudController@downloadTemplate')->name('seguimiento-nom.downloadTemplate');
    
    
    Route::crud('autorizacion', 'AutorizacionCrudController');
    Route::get('autorizacion/fetch-persona', 'AutorizacionCrudController@fetchPersonaFilter')
        ->name('autorizacion.fetchPersonaFilter');
    Route::post('autorizacion/{id}/estado-aprobacion', 'AutorizacionCrudController@updateEstadoAprobacion')
        ->name('autorizacion.updateEstadoAprobacion');
    Route::post('autorizacion/{id}/toggle-planeacion', 'AutorizacionCrudController@togglePlaneacion')
        ->name('autorizacion.togglePlaneacion');
    Route::crud('programas', 'ProgramasCrudController');
    Route::crud('equipo-campania', 'EquipoCampaniaCrudController');

    Route::group(['middleware' => ['role:coordinador,coordinador_comite']], function () {
        Route::get('reportar-equipo', 'ReportarEquipoController@index')->name('reportar-equipo.index');
        Route::get('reportar-equipo/reportar', 'ReportarEquipoController@create')->name('reportar-equipo.create');
        Route::get('reportar-equipo/editar', 'ReportarEquipoController@edit')->name('reportar-equipo.edit');
        Route::post('reportar-equipo/buscar', 'ReportarEquipoController@buscar')->name('reportar-equipo.buscar');
        Route::post('reportar-equipo', 'ReportarEquipoController@store')->name('reportar-equipo.store');
        Route::post('reportar-equipo/actualizar', 'ReportarEquipoController@updateMember')->name('reportar-equipo.update');
        Route::post('reportar-equipo/remove', 'ReportarEquipoController@remove')->name('reportar-equipo.remove');
    });
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
