<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\GerenciaController;
use App\Http\Controllers\Admin\PersonaExportController;



Route::get('/', function () {
    return redirect('/admin');
});

Route::get('admin/gerencias-por-secretaria/{secretaria}', [GerenciaController::class, 'getGerenciasPorSecretaria']);


Route::get('admin/persona/export', [PersonaExportController::class, 'export'])->name('persona.export');


Route::get('seguimiento/{id}/print', function ($id) {
    $seguimiento = \App\Models\Seguimiento::findOrFail($id);
    return view('seguimiento.print', compact('seguimiento'));
});

