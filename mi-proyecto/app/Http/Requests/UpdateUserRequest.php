<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

/**
 * Request de validación para actualizar usuario
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo superadmins pueden actualizar usuarios
        return $this->user() && $this->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user'); // ID del usuario siendo editado

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:191',
                'min:3',
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => [
                'sometimes',
                'required',
                Rule::in([
                    User::ROLE_ARCHIVIST,
                    User::ROLE_READER,
                ]),
                // NO se permite cambiar a superadmin
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    User::STATUS_ACTIVE,
                    User::STATUS_DISABLED,
                    User::STATUS_INVITED,
                ]),
            ],
            'reason' => [
                'required_if:status,' . User::STATUS_DISABLED,
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',

            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Debe proporcionar un email válido.',
            'email.unique' => 'Este email ya está registrado en el sistema.',

            'role.required' => 'Debe seleccionar un rol.',
            'role.in' => 'El rol seleccionado no es válido.',

            'status.required' => 'Debe seleccionar un estado.',
            'status.in' => 'El estado seleccionado no es válido.',

            'reason.required_if' => 'Debe proporcionar un motivo al deshabilitar un usuario.',
            'reason.max' => 'El motivo no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'status' => 'estado',
            'reason' => 'motivo',
        ];
    }
}
