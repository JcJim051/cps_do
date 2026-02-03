<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgramaRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'operador' => 'required|string|max:255',
            'municipio' => 'required|string|max:255',
            'institucion' => 'required|string|max:255',
            'nombre_apellidos' => 'required|string|max:255',
            'cedula' => 'required|string|max:20',
            'foto' => 'nullable|image|max:2048',
        ];
    }
}
