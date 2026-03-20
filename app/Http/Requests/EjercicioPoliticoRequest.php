<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EjercicioPoliticoRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
        ];
    }
}
