<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EquipoCampaniaRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        $user = backpack_user();

        $rules = [
            'ejercicio_politico_id' => 'required|exists:ejercicios_politicos,id',
            'coordinador_user_id' => 'required|exists:users,id',
            'nombre' => 'required|string|max:255',
        ];

        if ($user && !$user->hasAnyRole(['admin', 'diana'])) {
            $rules['ejercicio_politico_id'] = 'sometimes|exists:ejercicios_politicos,id';
            $rules['coordinador_user_id'] = 'sometimes|exists:users,id';
            $rules['nombre'] = 'sometimes|string|max:255';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre del equipo es obligatorio.',
            'ejercicio_politico_id.required' => 'La campaña es obligatoria.',
            'coordinador_user_id.required' => 'El coordinador es obligatorio.',
        ];
    }
}
