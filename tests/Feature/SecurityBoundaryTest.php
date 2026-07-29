<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Configuracao;
use App\Models\Setor;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_is_redirected_to_audit_instead_of_receiving_operational_dashboard(): void
    {
        $setor = $this->criarSetor('Setor Sigiloso', 'SIG');
        $auditor = $this->criarUsuario($setor, UserRole::AUDITOR, 'auditor@example.test');

        $this->actingAs($auditor)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.audit.index'));
    }

    public function test_configuration_view_exposes_only_the_explicit_editable_catalog(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        Configuracao::set('smtp_password', 'segredo-que-nao-pode-vazar', 'Credencial interna');

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.index'))
            ->assertOk()
            ->assertSee('sla_dias_conferencia')
            ->assertDontSee('smtp_password')
            ->assertDontSee('segredo-que-nao-pode-vazar');
    }

    public function test_unknown_configuration_cannot_be_created_by_forged_request(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->from(route('admin.configuracoes.index'))
            ->put(route('admin.configuracoes.update'), $this->configuracoesValidas() + [
                'smtp_password' => 'valor-injetado',
            ])
            ->assertRedirect(route('admin.configuracoes.index'))
            ->assertSessionHasErrors('configuracoes');

        $this->assertDatabaseMissing('configuracoes', ['chave' => 'smtp_password']);
    }

    public function test_configuration_business_ranges_are_validated_before_persistence(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');

        $dados = $this->configuracoesValidas();
        $dados['sla_dias_conferencia'] = 5;
        $dados['sla_dias_alerta'] = 6;

        $this->actingAs($admin)
            ->put(route('admin.configuracoes.update'), $dados)
            ->assertSessionHasErrors('sla_dias_alerta');

        $this->assertNotSame('6', Configuracao::get('sla_dias_alerta'));
    }

    public function test_only_admin_can_update_configuration_and_audit_does_not_store_values(): void
    {
        $setor = $this->criarSetor();
        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central@example.test');
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        $dados = $this->configuracoesValidas();
        $dados['email_usuario_sistema'] = 'rotina-interna@example.test';

        $this->actingAs($central)
            ->put(route('admin.configuracoes.update'), $dados)
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.configuracoes.update'), $dados)
            ->assertRedirect(route('admin.configuracoes.index'));

        $this->assertSame('rotina-interna@example.test', Configuracao::get('email_usuario_sistema'));

        $auditoria = AuditLog::query()
            ->where('modelo', 'Configuracao')
            ->where('descricao', "Configuração 'email_usuario_sistema' atualizada.")
            ->firstOrFail();

        $this->assertNull($auditoria->dados_antes);
        $this->assertNull($auditoria->dados_depois);
        $this->assertStringNotContainsString('rotina-interna@example.test', $auditoria->descricao);
    }

    public function test_historical_configuration_values_are_redacted_from_audit_view(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        $this->actingAs($admin);

        AuditService::registrar(
            'EDITOU',
            'Configuracao',
            999,
            "Configuração 'smtp_password' alterada de 'antiga' para 'credencial-ultrassecreta'."
        );

        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Configuração de sistema alterada.')
            ->assertDontSee('smtp_password')
            ->assertDontSee('credencial-ultrassecreta');
    }

    public function test_user_audit_records_never_contain_password_or_remember_token(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        $this->actingAs($admin);
        $service = app(UserService::class);

        $usuario = $service->create([
            'name' => 'Usuário auditado',
            'email' => 'auditado@example.test',
            'password' => 'senha-inicial-secreta',
            'setor_id' => $setor->id,
            'role' => UserRole::SETORIAL,
        ]);
        $service->update($usuario, [
            'name' => 'Usuário auditado',
            'email' => 'auditado@example.test',
            'password' => 'senha-nova-secreta',
            'setor_id' => $setor->id,
            'role' => UserRole::SETORIAL,
        ]);

        $auditorias = AuditLog::query()
            ->where('modelo', 'User')
            ->where('modelo_id', $usuario->id)
            ->get();

        $this->assertCount(2, $auditorias);
        foreach ($auditorias as $auditoria) {
            $conteudo = json_encode(
                [$auditoria->dados_antes, $auditoria->dados_depois],
                JSON_THROW_ON_ERROR
            );
            $this->assertStringNotContainsString('password', $conteudo);
            $this->assertStringNotContainsString('remember_token', $conteudo);
            $this->assertStringNotContainsString('senha-inicial-secreta', $conteudo);
            $this->assertStringNotContainsString('senha-nova-secreta', $conteudo);
        }

        $this->assertTrue(
            AuditLog::query()
                ->where('modelo', 'User')
                ->where('modelo_id', $usuario->id)
                ->where('acao', 'EDITOU')
                ->firstOrFail()
                ->dados_depois['senha_alterada']
        );
    }

    private function configuracoesValidas(): array
    {
        return [
            'sla_dias_conferencia' => 5,
            'sla_dias_alerta' => 3,
            'limite_rejeicoes_lancamento' => 3,
            'teto_adicional_noturno' => '500.00',
            'meses_retroativos' => 3,
            'limite_orcamento_retroativo' => '',
            'limite_delegacoes_setor' => 3,
            'duracao_maxima_delegacao_dias' => 90,
            'limite_valor_total_servidor' => '',
            'transferir_lancamentos_ao_mudar_setor' => 'false',
            'email_usuario_sistema' => 'admin@example.test',
        ];
    }

    private function criarSetor(string $nome = 'Setor de Teste', string $sigla = 'TST'): Setor
    {
        return Setor::create([
            'nome' => $nome,
            'sigla' => $sigla,
            'ativo' => true,
        ]);
    }

    private function criarUsuario(Setor $setor, UserRole $role, string $email): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $email,
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => $role,
        ]);
    }
}
