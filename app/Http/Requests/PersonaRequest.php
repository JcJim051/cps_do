<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Obtiene el ID del registro actual (si es update)
        $personaId = $this->route('id') ?? null;

        return [
            'nombre_contratista' => 'required|string|max:255',
            'cedula_o_nit' => 'required|string|max:50|unique:personas,cedula_o_nit' . ($personaId ? ",$personaId,id" : ''),
            'celular' => 'nullable|string|max:30',
            'genero' => 'nullable|in:Masculino,Femenino',
            'nivel_academico_id' => 'nullable|exists:niveles_academicos,id',
            'tecnico_tecnologo_profesion' => 'nullable|string|max:255',
            'especializacion' => 'nullable|string|max:255',
            'maestria' => 'nullable|string|max:255',
            'referencia_id' => 'nullable|exists:referencias,id',
            'estado' => 'nullable|string|max:100',
            'foto' => 'nullable|image|max:2048', // 2MB
            'documento_pdf' => 'nullable|mimes:pdf|max:5120', // 5MB
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'cedula_o_nit.unique' => 'La cédula o NIT ya ha sido registrada en otro registro.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'documento_pdf.mimes' => 'El archivo debe ser un PDF válido.',
        ];
    }
}
