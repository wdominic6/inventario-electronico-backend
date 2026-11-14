<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id_usuario ?? $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('usuarios', 'email')->ignore($userId, 'id_usuario'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'role' => ['sometimes', 'required', 'string', 'in:admin,almacen,ventas'],
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:sucursales,id_sucursal'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del usuario no puede estar vacío.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Este correo electrónico ya está asignado a otro usuario.',
            'password.min' => 'La contraseña debe contener como mínimo :min caracteres.',
            'role.in' => 'El rol seleccionado no es válido. Valores permitidos: admin, almacen, ventas.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
