<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\Authorization\AuthorizesRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

/**
 * Request de validación para crear usuario
 */
class CreateUserRequest extends FormRequest
{
    use AuthorizesRequests;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo superadmins pueden crear usuarios
        return $this->user() && $this->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                'min:3',
            ],
            'email' => [
                'required',
                'email',
                'max:191',
                'unique:users,email', // Email debe ser único
            ],
            'username' => [
                'required',
                'string',
                'max:100',
                'min:3',
                'regex:/^[a-zA-Z0-9_-]+$/', // Solo alfanuméricos, guiones y guión bajo
                // NO validamos unique aquí porque permitimos reutilización
            ],
            'role' => [
                'required',
                Rule::in([
                    User::ROLE_ARCHIVIST,
                    User::ROLE_READER,
                ]),
                /// NO se permite crear superadmin
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
            'name.max' => 'El nombre no puede exceder 191 caracteres.',
            
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Debe proporcionar un email válido.',
            'email.unique' => 'Este email ya está registrado en el sistema.',
            
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.min' => 'El username debe tener al menos 3 caracteres.',
            'username.regex' => 'El username solo puede contener letras, números, guiones y guión bajo.',
            
            'role.required' => 'Debe seleccionar un rol.',
            'role.in' => 'El rol seleccionado no es válido. Solo se permite archivista o lector.',
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
            'username' => 'nombre de usuario',
            'role' => 'rol',
        ];
    }
}
