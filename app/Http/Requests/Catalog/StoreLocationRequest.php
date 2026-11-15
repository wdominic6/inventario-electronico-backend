<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'code' => ['required', 'string', 'max:50', 'unique:ubicaciones,codigo'],
            'shelf' => ['required', 'string', 'max:50'],
            'level' => ['required', 'string', 'max:50'],
            'bin' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Debe seleccionar la sucursal a la que pertenece la ubicación.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'code.required' => 'El código de ubicación física es obligatorio.',
            'code.unique' => 'Ya existe una ubicación con este código en la sucursal seleccionada.',
            'shelf.required' => 'El estante (shelf) es obligatorio.',
            'level.required' => 'El nivel (level) es obligatorio.',
            'bin.required' => 'El casillero o gaveta (bin) es obligatorio.',
        ];
    }
}
