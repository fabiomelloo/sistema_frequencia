<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'setor_id' => ['required', 'exists:setores,id'],
            'role' => ['required', 'in:' . implode(',', \App\Enums\UserRole::valores())],
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
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
            'setor_id.required' => 'O setor é obrigatório.',
            'setor_id.exists' => 'Setor inválido.',
            'role.required' => 'O perfil é obrigatório.',
            'role.in' => 'Perfil inválido. Deve ser SETORIAL ou CENTRAL.',
        ];
    }
}
