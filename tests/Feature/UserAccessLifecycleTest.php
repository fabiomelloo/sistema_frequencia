<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deactivates_account_without_deleting_history_and_can_reactivate_it(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial@example.test');
        DB::table('sessions')->insert([
            'id' => 'sessao-setorial',
            'user_id' => $usuario->id,
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $usuario))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'ativo' => false,
            'desativado_por_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-setorial']);
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'DESATIVOU',
            'modelo' => 'User',
            'modelo_id' => $usuario->id,
        ]);

        Auth::logout();
        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'SenhaSegura123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)
            ->patch(route('admin.users.ativar', $usuario))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'ativo' => true]);
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'REATIVOU',
            'modelo_id' => $usuario->id,
        ]);
    }

    public function test_repeated_failures_temporarily_lock_account_and_success_resets_state(): void
    {
        config([
            'operations.authentication.max_failed_attempts' => 2,
            'operations.authentication.lockout_minutes' => 15,
        ]);
        $usuario = $this->criarUsuario($this->criarSetor(), UserRole::SETORIAL, 'bloqueio@example.test');

        foreach (range(1, 2) as $tentativa) {
            $this->post('/login', [
                'email' => $usuario->email,
                'password' => "SenhaIncorreta{$tentativa}",
            ])->assertSessionHasErrors('email');
        }

        $this->assertTrue($usuario->fresh()->estaBloqueado());
        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'SenhaSegura123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $usuario->forceFill(['bloqueado_ate' => now()->subMinute()])->save();
        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'SenhaSegura123',
        ])->assertRedirect(route('dashboard'));

        $usuario->refresh();
        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(0, $usuario->tentativas_login_falhas);
        $this->assertNull($usuario->bloqueado_ate);
        $this->assertNotNull($usuario->ultimo_login_em);
        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'LOGIN_FALHOU',
            'modelo' => 'User',
            'modelo_id' => $usuario->id,
        ]);
    }

    private function criarSetor(): Setor
    {
        return Setor::create([
            'nome' => 'Setor de Teste',
            'sigla' => 'TST',
            'ativo' => true,
        ]);
    }

    private function criarUsuario(Setor $setor, UserRole $role, string $email): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $email,
            'password' => 'SenhaSegura123',
            'setor_id' => $setor->id,
            'role' => $role,
            'ativo' => true,
        ]);
    }
}
