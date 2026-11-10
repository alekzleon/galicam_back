<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'username', 'email'] as $field) {
            if ($this->has($field)) {
                $value = $this->filled($field) ? trim((string) $this->input($field)) : null;
                $data[$field] = $field === 'email' && $value ? strtolower($value) : $value;
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('id');

        return [
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('name', '!=', 'cliente')),
            ],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.exists' => 'El rol seleccionado no es válido para usuarios internos.',
            'region_id.exists' => 'El centro regional seleccionado no existe.',
            'name.required' => 'El nombre es obligatorio.',
            'username.required' => 'El usuario es obligatorio.',
            'username.unique' => 'El usuario ya está en uso.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'El correo ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->route('user');
            $roleId = $this->has('role_id') ? $this->input('role_id') : $user?->role_id;
            $regionId = $this->has('region_id') ? $this->input('region_id') : $user?->region_id;
            $roleName = Role::query()
                ->whereKey($roleId)
                ->value('name');

            if ($roleName === 'centro_regional_admin' && blank($regionId)) {
                $validator->errors()->add('region_id', 'El centro regional es obligatorio para un administrador regional.');
            }
        });
    }
}
