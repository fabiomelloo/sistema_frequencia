<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    /**
     * Cria um novo usuário com senha hashada.
     */
    public function create(array $data): User
    {
        // Garante que a senha seja hashada (embora o cast 'hashed' no Model já faça isso em Laravel 10+,
        // é boa prática explicitar ou manipular aqui se houver lógica extra como envio de email)
        
        // Log para auditoria (melhoria de segurança)
        Log::info('Novo usuário criado', [
            'email' => $data['email'],
            'role' => $data['role'],
            'criado_por' => auth()->id()
        ]);

        return User::create($data);
    }

    /**
     * Atualiza usuário, tratando lógica de senha vazia.
     */
    public function update(User $user, array $data): bool
    {
        // Lógica de senha vazia movida do Controller para cá
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            // Se senha fornecida, garante hash ou confia no cast do model
            // $data['password'] = Hash::make($data['password']); // Opcional se usar cast
        }

        $updated = $user->update($data);

        Log::info('Usuário atualizado', [
            'user_id' => $user->id,
            'atualizado_por' => auth()->id()
        ]);

        return $updated;
    }
}
