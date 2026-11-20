<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAuditLogRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'exists:usuarios,id_usuario'],
            'action' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date', Rule::when($this->filled('date_to'), ['before_or_equal:date_to'])],
            'date_to' => ['nullable', 'date', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'El identificador de usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario especificado no existe.',
            'date_from.date' => 'La fecha inicial debe tener un formato válido.',
            'date_from.before_or_equal' => 'La fecha inicial no puede ser posterior a la fecha final.',
            'date_to.date' => 'La fecha final debe tener un formato válido.',
            'date_to.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'per_page.min' => 'El tamaño de página debe ser al menos 1.',
            'per_page.max' => 'El tamaño de página no puede exceder 100 registros.',
        ];
    }
}
