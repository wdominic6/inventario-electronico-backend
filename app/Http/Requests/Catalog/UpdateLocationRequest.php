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
        $locationId = is_object($location) ? $location->id : $location;
        $branchId = $this->input('branch_id', is_object($location) ? $location->branch_id : null);

        return [
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('locations', 'code')
                    ->where(fn ($query) => $query->where('branch_id', $branchId))
                    ->ignore($locationId),
            ],
            'shelf' => ['sometimes', 'required', 'string', 'max:50'],
            'level' => ['sometimes', 'required', 'string', 'max:50'],
            'bin' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
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
