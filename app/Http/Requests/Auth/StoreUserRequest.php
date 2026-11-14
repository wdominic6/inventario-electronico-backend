<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:usuarios,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', 'string', 'in:admin,almacen,ventas'],
            'branch_id' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo del usuario es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario registrado con este correo electrónico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe contener como mínimo :min caracteres.',
            'role.required' => 'El rol del usuario es obligatorio.',
            'role.in' => 'El rol seleccionado no es válido. Valores permitidos: admin, almacen, ventas.',
            'branch_id.required' => 'La sucursal del usuario es obligatoria.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
