<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user && ($this->user()?->can('update', $user) ?? false);
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$userId],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'setor_id' => ['required', 'exists:setores,id'],
            'role' => ['required', 'in:'.implode(',', UserRole::valores())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Digite um email válido.',
            'email.unique' => 'Este email já está cadastrado.',
            'password.min' => 'A senha deve ter no mínimo 12 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
            'setor_id.required' => 'O setor é obrigatório.',
            'setor_id.exists' => 'Setor inválido.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'Perfil inválido. Deve ser SETORIAL ou CENTRAL.',
        ];
    }
}
