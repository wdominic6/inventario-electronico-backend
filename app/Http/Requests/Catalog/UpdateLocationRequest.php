<?php

namespace App\Http\Requests\Catalog;

use App\Models\Ubicacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $locationId = $location instanceof Ubicacion ? $location->id_ubicacion : $location;

        return [
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:sucursales,id_sucursal'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('ubicaciones', 'codigo')->ignore($locationId, 'id_ubicacion')],
            'shelf' => ['sometimes', 'required', 'string', 'max:50'],
            'level' => ['sometimes', 'required', 'string', 'max:50'],
            'bin' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $location = $this->route('location');
            if (! $location instanceof Ubicacion) {
                return;
            }

            $branchId = (int) ($this->input('branch_id') ?? $location->id_sucursal);
            $shelf = (string) ($this->input('shelf') ?? $location->estante);
            $level = (string) ($this->input('level') ?? $location->nivel);
            $bin = (string) ($this->input('bin') ?? $location->casillero);

            $exists = DB::table('ubicaciones')
                ->where('id_sucursal', $branchId)
                ->where('estante', $shelf)
                ->where('nivel', $level)
                ->where('casillero', $bin)
                ->where('id_ubicacion', '!=', $location->id_ubicacion)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'bin',
                    'Ya existe otra ubicación registrada con el mismo estante, nivel y casillero en esta sucursal.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'code.required' => 'El código de ubicación física no puede estar vacío.',
            'code.unique' => 'Ya existe otra ubicación con este código.',
            'shelf.required' => 'El estante (shelf) no puede estar vacío.',
            'level.required' => 'El nivel (level) no puede estar vacío.',
            'bin.required' => 'El casillero (bin) no puede estar vacío.',
        ];
    }
}
