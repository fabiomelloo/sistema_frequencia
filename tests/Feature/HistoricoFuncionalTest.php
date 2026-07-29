<?php

namespace Tests\Feature;

use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use App\Enums\UserRole;
use App\Enums\VinculoServidor;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricoFuncionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_servidor_is_created_with_initial_functional_and_lotacao_history(): void
    {
        $setor = $this->criarSetor('RH');
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin-historico@example.test');

        $this->actingAs($admin)->post(route('admin.servidores.store'), [
            'matricula' => '10001',
            'nome' => 'Ana Funcional',
            'setor_id' => $setor->id,
            'data_admissao' => '2025-01-15',
            'tipo_vinculo' => VinculoServidor::EFETIVO->value,
            'cargo' => 'Assistente Administrativo',
            'carga_horaria' => 40,
            'ato_referencia' => 'Portaria 15/2025',
        ])->assertRedirect(route('admin.servidores.index'))->assertSessionHasNoErrors();

        $servidor = Servidor::firstOrFail();
        $this->assertSame('Assistente Administrativo', $servidor->cargo);
        $this->assertSame(VinculoServidor::EFETIVO, $servidor->vinculo);
        $this->assertDatabaseHas('vinculos_funcionais', [
            'servidor_id' => $servidor->id,
            'matricula' => '10001',
            'cargo' => 'Assistente Administrativo',
            'data_inicio' => '2025-01-15',
            'data_fim' => null,
        ]);
        $this->assertDatabaseHas('lotacao_historico', [
            'servidor_id' => $servidor->id,
            'setor_id' => $setor->id,
            'data_inicio' => '2025-01-15',
            'data_fim' => null,
        ]);
    }

    public function test_new_functional_situation_closes_previous_period_without_losing_history(): void
    {
        [$admin, $servidor] = $this->criarServidorComHistorico();

        $this->actingAs($admin)->post(route('admin.servidores.historico.vinculos.store', $servidor), [
            'tipo_vinculo' => VinculoServidor::COMISSIONADO->value,
            'cargo' => 'Coordenador Administrativo',
            'carga_horaria' => 40,
            'data_inicio' => '2026-02-01',
            'ato_referencia' => 'Decreto 20/2026',
        ])->assertRedirect(route('admin.servidores.historico', $servidor))->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame('Coordenador Administrativo', $servidor->cargo);
        $this->assertSame(VinculoServidor::COMISSIONADO, $servidor->vinculo);
        $this->assertSame('Assistente Administrativo', $servidor->vinculoFuncionalEm('2026-01-15')?->cargo);
        $this->assertSame('Coordenador Administrativo', $servidor->vinculoFuncionalEm('2026-02-15')?->cargo);
        $this->assertDatabaseHas('vinculos_funcionais', [
            'servidor_id' => $servidor->id,
            'cargo' => 'Assistente Administrativo',
            'data_fim' => '2026-01-31',
        ]);
    }

    public function test_designation_periods_cannot_overlap_and_can_be_closed(): void
    {
        [$admin, $servidor] = $this->criarServidorComHistorico();
        $dados = [
            'tipo' => 'FGT',
            'nivel' => '3',
            'descricao' => 'Chefia de seção',
            'data_inicio' => '2026-03-01',
            'ato_referencia' => 'Portaria 30/2026',
        ];

        $this->actingAs($admin)->post(route('admin.servidores.historico.designacoes.store', $servidor), $dados)
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.servidores.historico.designacoes.store', $servidor), $dados + ['data_inicio' => '2026-04-01'])
            ->assertSessionHasErrors('vigencia');

        $designacao = $servidor->designacoesFuncionais()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.servidores.historico.designacoes.encerrar', [$servidor, $designacao]), [
            'data_fim' => '2026-05-31',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('designacoes_funcionais', ['id' => $designacao->id, 'data_fim' => '2026-05-31']);
    }

    public function test_only_validated_functional_catalog_items_can_be_registered_as_advantages(): void
    {
        [$admin, $servidor] = $this->criarServidorComHistorico();
        $itemFuncional = $this->criarEvento('INS-FUNC', OrigemInformacaoItem::CADASTRO_FUNCIONAL, true);
        $itemMensal = $this->criarEvento('INS-MES', OrigemInformacaoItem::SETOR_MENSAL, true);

        $this->actingAs($admin)->post(route('admin.servidores.historico.vantagens.store', $servidor), [
            'evento_id' => $itemFuncional->id,
            'percentual' => 20,
            'valor' => 999,
            'data_inicio' => '2026-03-01',
            'referencia_documento' => 'Laudo 44/2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vantagens_funcionais', [
            'servidor_id' => $servidor->id,
            'evento_id' => $itemFuncional->id,
            'percentual' => 20,
            'valor' => null,
            'referencia_documento' => 'Laudo 44/2026',
        ]);

        $this->actingAs($admin)->post(route('admin.servidores.historico.vantagens.store', $servidor), [
            'evento_id' => $itemMensal->id,
            'percentual' => 20,
            'data_inicio' => '2026-03-01',
            'referencia_documento' => 'Laudo 45/2026',
        ])->assertSessionHasErrors('evento_id');

        $this->assertDatabaseCount('vantagens_funcionais', 1);
    }

    public function test_historical_page_is_accessible_to_central_and_contains_governed_empty_state(): void
    {
        [$central, $servidor] = $this->criarServidorComHistorico(UserRole::CENTRAL);

        $this->actingAs($central)->get(route('admin.servidores.historico', $servidor))
            ->assertOk()
            ->assertSee('Histórico funcional')
            ->assertSee('Ainda não há item de cadastro funcional validado');
    }

    public function test_deactivation_and_reactivation_close_and_reopen_functional_periods(): void
    {
        [$admin, $servidor] = $this->criarServidorComHistorico();
        $servidor->lotacoes()->create([
            'setor_id' => $servidor->setor_id,
            'data_inicio' => '2025-01-01',
        ]);

        $this->actingAs($admin)->post(route('admin.servidores.desligar', $servidor), [
            'data_desligamento' => now()->toDateString(),
            'motivo' => 'APOSENTADORIA',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('vinculos_funcionais', ['servidor_id' => $servidor->id, 'data_fim' => null]);
        $this->assertDatabaseMissing('lotacao_historico', ['servidor_id' => $servidor->id, 'data_fim' => null]);

        $servidor->update(['data_desligamento' => now()->subDay(), 'ativo' => false]);
        $this->actingAs($admin)->post(route('admin.servidores.ativar', $servidor))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vinculos_funcionais', [
            'servidor_id' => $servidor->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => null,
        ]);
        $this->assertDatabaseHas('lotacao_historico', [
            'servidor_id' => $servidor->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => null,
        ]);
    }

    /** @return array{User, Servidor} */
    private function criarServidorComHistorico(UserRole $role = UserRole::ADMIN): array
    {
        $setor = $this->criarSetor('ADM');
        $usuario = $this->criarUsuario($setor, $role, strtolower($role->value).'-funcional@example.test');
        $servidor = Servidor::create([
            'matricula' => '20001',
            'nome' => 'Servidor Histórico',
            'setor_id' => $setor->id,
            'data_admissao' => '2025-01-01',
            'vinculo' => VinculoServidor::EFETIVO,
            'cargo' => 'Assistente Administrativo',
            'carga_horaria' => 40,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $servidor->vinculosFuncionais()->create([
            'matricula' => $servidor->matricula,
            'tipo_vinculo' => VinculoServidor::EFETIVO,
            'cargo' => 'Assistente Administrativo',
            'carga_horaria' => 40,
            'data_inicio' => '2025-01-01',
            'registrado_por_id' => $usuario->id,
        ]);

        return [$usuario, $servidor];
    }

    private function criarEvento(string $codigo, OrigemInformacaoItem $origem, bool $validado): EventoFolha
    {
        return EventoFolha::create([
            'codigo_evento' => $codigo,
            'sigla' => 'INS',
            'descricao' => 'Insalubridade funcional',
            'tipo_evento' => TipoEvento::INSALUBRIDADE,
            'unidade_lancamento' => UnidadeLancamento::PERCENTUAL,
            'origem_informacao' => $origem,
            'gera_efeito_financeiro' => true,
            'exige_porcentagem' => true,
            'exige_documento' => true,
            'instrucoes_lancamento' => 'Aplicar conforme laudo vigente e homologado.',
            'regra_validada' => $validado,
            'ativo' => true,
        ]);
    }

    private function criarSetor(string $sigla): Setor
    {
        return Setor::create(['nome' => "Setor {$sigla}", 'sigla' => $sigla, 'ativo' => true]);
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
