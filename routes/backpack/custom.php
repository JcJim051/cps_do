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

        Route::get('prevalidacion-contractual', 'PrevalidacionContractualController@index')->name('prevalidacion.index');
        Route::get('prevalidacion-contractual/{prevalidacion}', 'PrevalidacionContractualController@show')->whereNumber('prevalidacion')->name('prevalidacion.show');
        Route::get('prevalidacion-contractual/{prevalidacion}/editar', 'PrevalidacionContractualController@edit')->whereNumber('prevalidacion')->name('prevalidacion.edit');
        Route::put('prevalidacion-contractual/{prevalidacion}', 'PrevalidacionContractualController@update')->whereNumber('prevalidacion')->name('prevalidacion.update');
        Route::post('prevalidacion-contractual/estado-masivo', 'PrevalidacionContractualController@bulkState')->name('prevalidacion.bulk-state');
        Route::post('prevalidacion-contractual/{prevalidacion}/aceptar-drive', 'PrevalidacionContractualController@acceptDrive')->whereNumber('prevalidacion')->name('prevalidacion.accept-drive');
        Route::post('prevalidacion-contractual/promover', 'PrevalidacionContractualController@promote')->name('prevalidacion.promote');
        Route::post('prevalidacion-contractual/reintentar-drive', 'PrevalidacionContractualController@retryDrive')->name('prevalidacion.retry-drive');
        Route::get('prevalidacion-contractual/{prevalidacion}/candidatos-secop', 'PrevalidacionContractualController@candidates')->whereNumber('prevalidacion')->name('prevalidacion.candidates');
        Route::post('prevalidacion-contractual/{prevalidacion}/vincular-secop', 'PrevalidacionContractualController@link')->whereNumber('prevalidacion')->name('prevalidacion.link');
        Route::delete('prevalidacion-contractual/{prevalidacion}/vinculo-secop', 'PrevalidacionContractualController@unlink')->whereNumber('prevalidacion')->name('prevalidacion.unlink');
        Route::post('prevalidacion-contractual/{prevalidacion}/refrescar-secop', 'PrevalidacionContractualController@refresh')->whereNumber('prevalidacion')->name('prevalidacion.refresh');

        Route::get('prevalidacion-fuentes', 'PrevalidacionFuenteController@index')->name('prevalidacion.sources.index');
        Route::get('prevalidacion-fuentes/crear', 'PrevalidacionFuenteController@create')->name('prevalidacion.sources.create');
        Route::post('prevalidacion-fuentes', 'PrevalidacionFuenteController@store')->name('prevalidacion.sources.store');
        Route::get('prevalidacion-fuentes/{fuente}/editar', 'PrevalidacionFuenteController@edit')->whereNumber('fuente')->name('prevalidacion.sources.edit');
        Route::put('prevalidacion-fuentes/{fuente}', 'PrevalidacionFuenteController@update')->whereNumber('fuente')->name('prevalidacion.sources.update');
        Route::post('prevalidacion-fuentes/{fuente}/sincronizar', 'PrevalidacionFuenteController@sync')->whereNumber('fuente')->name('prevalidacion.sources.sync');
        Route::post('prevalidacion-fuentes/sincronizar-todas', 'PrevalidacionFuenteController@syncAll')->name('prevalidacion.sources.sync-all');
        Route::post('prevalidacion-fuentes/importar', 'PrevalidacionFuenteController@import')->name('prevalidacion.sources.import');
        Route::get('prevalidacion-fuentes/plantilla', 'PrevalidacionFuenteController@template')->name('prevalidacion.sources.template');
        Route::get('prevalidacion-google/conectar', 'GooglePrevalidacionController@redirect')->name('prevalidacion.google.redirect');
        Route::get('prevalidacion-google/configuracion', 'GooglePrevalidacionController@configuration')->name('prevalidacion.google.configuration');
        Route::put('prevalidacion-google/configuracion', 'GooglePrevalidacionController@saveCredentials')->name('prevalidacion.google.credentials');
        Route::get('prevalidacion-google/callback', 'GooglePrevalidacionController@callback')->name('prevalidacion.google.callback');
        Route::delete('prevalidacion-google/desconectar', 'GooglePrevalidacionController@disconnect')->name('prevalidacion.google.disconnect');
        Route::get('seguimiento/{seguimiento}/candidatos-secop', 'SeguimientoSecopController@candidates')->whereNumber('seguimiento')->name('seguimiento.secop.candidates');
        Route::post('seguimiento/{seguimiento}/vincular-secop', 'SeguimientoSecopController@link')->whereNumber('seguimiento')->name('seguimiento.secop.link');
        Route::delete('seguimiento/{seguimiento}/vinculo-secop', 'SeguimientoSecopController@unlink')->whereNumber('seguimiento')->name('seguimiento.secop.unlink');
        Route::post('seguimiento/{seguimiento}/refrescar-secop', 'SeguimientoSecopController@refresh')->whereNumber('seguimiento')->name('seguimiento.secop.refresh');
        Route::post('seguimiento/{seguimiento}/secop/campo/{field}/restaurar', 'SeguimientoSecopController@restoreField')->whereNumber('seguimiento')->name('seguimiento.secop.restore-field');
        Route::post('seguimiento/{seguimiento}/secop/actualizacion/{actualizacion}/revertir', 'SeguimientoSecopController@revert')->whereNumber('seguimiento')->whereNumber('actualizacion')->name('seguimiento.secop.revert');
        Route::post('seguimiento/{seguimiento}/secop/sincronizacion-automatica', 'SeguimientoSecopController@toggleAutomatic')->whereNumber('seguimiento')->name('seguimiento.secop.toggle-automatic');
        Route::post('persona/{persona}/conciliacion-secop/vincular-exactos', 'PersonaSecopController@linkExact')->whereNumber('persona')->name('persona.secop.link-exact');
        Route::post('persona/{persona}/conciliacion-secop/sincronizar', 'PersonaSecopController@sync')->whereNumber('persona')->name('persona.secop.sync');
        Route::get('sincronizacion-secop', 'SecopSincronizacionController@index')->name('secop.sync.index');
        Route::post('sincronizacion-secop/aplicar', 'SecopSincronizacionController@apply')->name('secop.sync.apply');

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
    Route::post('autorizacion/{id}/toggle-autorizacion', 'AutorizacionCrudController@toggleAutorizacion')
        ->name('autorizacion.toggleAutorizacion');
    Route::crud('programas', 'ProgramasCrudController');
    Route::get('programas/import', 'ProgramasCrudController@importForm')->name('programas.importForm');
    Route::post('programas/import', 'ProgramasCrudController@import')->name('programas.import');
    Route::get('programas/template', 'ProgramasCrudController@downloadTemplate')->name('programas.downloadTemplate');
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
