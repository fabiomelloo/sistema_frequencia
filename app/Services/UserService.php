<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create($data);
            AuditService::registrar(
                'CRIOU',
                'User',
                $user->id,
                'Conta de usuário criada.',
                null,
                $this->dadosAuditaveis($user)
            );

            return $user;
        });
    }

    public function update(User $user, array $data): bool
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data): bool {
            $antes = $this->dadosAuditaveis($user);
            $senhaAlterada = array_key_exists('password', $data);
            $updated = $user->update($data);

            if ($updated) {
                AuditService::registrar(
                    'EDITOU',
                    'User',
                    $user->id,
                    'Conta de usuário atualizada.',
                    $antes,
                    $this->dadosAuditaveis($user->fresh()) + ['senha_alterada' => $senhaAlterada]
                );
            }

            return $updated;
        });
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
            $antes = $this->dadosAuditaveis($user);
            $user->forceFill([
                'ativo' => false,
                'desativado_em' => now(),
                'desativado_por_id' => $responsavel->id,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditService::registrar(
                'DESATIVOU',
                'User',
                $user->id,
                'Conta de usuário desativada.',
                $antes,
                $this->dadosAuditaveis($user->fresh())
            );
        });
    }

    public function ativar(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $antes = $this->dadosAuditaveis($user);
            $user->forceFill([
                'ativo' => true,
                'desativado_em' => null,
                'desativado_por_id' => null,
                'tentativas_login_falhas' => 0,
                'bloqueado_ate' => null,
            ])->save();
            AuditService::registrar(
                'REATIVOU',
                'User',
                $user->id,
                'Conta de usuário reativada.',
                $antes,
                $this->dadosAuditaveis($user->fresh())
            );
        });
    }

    private function dadosAuditaveis(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'setor_id' => $user->setor_id,
            'role' => $user->role->value,
            'ativo' => $user->ativo,
        ];
    }
}
