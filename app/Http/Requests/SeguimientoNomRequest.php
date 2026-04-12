<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeguimientoNomRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'persona_id' => 'required|exists:personas,id',
            'secretaria_id' => 'required|exists:secretarias,id',
            'cargo_id' => 'required|exists:cargos,id',
            'tipo_vinculacion_id' => 'required|exists:tipos_vinculacion,id',
            'fecha_ingreso' => 'required|date',
            'salario' => 'required|numeric|min:0',
        ];
    }
}
