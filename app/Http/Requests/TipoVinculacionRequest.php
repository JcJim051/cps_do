<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TipoVinculacionRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'nombre' => 'required|string|max:255',
        ];
    }
}
