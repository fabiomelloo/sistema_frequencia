<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
            'criado_por' => auth()->id(),
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
            'atualizado_por' => auth()->id(),
        ]);

        return $updated;
    }

    public function desativar(User $user, User $responsavel): void
    {
        if ($user->is($responsavel)) {
            throw new InvalidArgumentException('Não é possível desativar a própria conta.');
        }

        if ($user->isAdmin() && User::where('ativo', true)->where('role', 'ADMIN')->count() <= 1) {
            throw new InvalidArgumentException('Não é possível desativar o último administrador ativo.');
        }

        DB::transaction(function () use ($user, $responsavel): void {
            $antes = $user->toArray();
            $user->forceFill([
                'ativo' => false,
                'desativado_em' => now(),
                'desativado_por_id' => $responsavel->id,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::registrar('DESATIVOU', 'User', $user->id, 'Conta de usuário desativada.', $antes, $user->fresh()->toArray());
        });
    }

    public function ativar(User $user): void
    {
        $antes = $user->toArray();
        $user->forceFill([
            'ativo' => true,
            'desativado_em' => null,
            'desativado_por_id' => null,
            'tentativas_login_falhas' => 0,
            'bloqueado_ate' => null,
        ])->save();
        AuditService::registrar('REATIVOU', 'User', $user->id, 'Conta de usuário reativada.', $antes, $user->fresh()->toArray());
    }
}
