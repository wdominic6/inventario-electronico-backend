<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
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
        $location = $this->route('location');

        return [
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:sucursales,id_sucursal'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('ubicaciones', 'codigo')->ignore($this->route('location')?->id_ubicacion ?? $this->route('location'), 'id_ubicacion')],
            'shelf' => ['sometimes', 'required', 'string', 'max:50'],
            'level' => ['sometimes', 'required', 'string', 'max:50'],
            'bin' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'code.required' => 'El código de ubicación física no puede estar vacío.',
            'code.unique' => 'Ya existe otra ubicación con este código en la sucursal indicada.',
            'shelf.required' => 'El estante (shelf) no puede estar vacío.',
            'level.required' => 'El nivel (level) no puede estar vacío.',
            'bin.required' => 'El casillero (bin) no puede estar vacío.',
        ];
    }
}
