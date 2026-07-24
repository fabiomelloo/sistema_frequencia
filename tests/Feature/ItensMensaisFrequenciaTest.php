<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoDesignacaoFuncional;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use App\Enums\UserRole;
use App\Enums\VinculoServidor;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\FolhaFrequenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItensMensaisFrequenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_frequency_snapshots_functional_state_at_period_end(): void
    {
        [$usuario, $setor, $servidor, $competencia] = $this->cenarioBase();
        $vinculoAntigo = $servidor->vinculosFuncionais()->create([
            'matricula' => $servidor->matricula,
            'tipo_vinculo' => VinculoServidor::EFETIVO,
            'cargo' => 'Assistente',
            'carga_horaria' => 30,
            'data_inicio' => '2020-01-01',
            'data_fim' => '2026-06-30',
        ]);
        $vinculoAtual = $servidor->vinculosFuncionais()->create([
            'matricula' => $servidor->matricula,
            'tipo_vinculo' => VinculoServidor::COMISSIONADO,
            'cargo' => 'Coordenador',
            'carga_horaria' => 40,
            'data_inicio' => '2026-07-01',
        ]);
        $servidor->designacoesFuncionais()->create([
            'tipo' => TipoDesignacaoFuncional::DAS,
            'nivel' => '5',
            'descricao' => 'Coordenação de unidade',
            'data_inicio' => '2026-07-01',
        ]);
        $eventoFuncional = $this->criarEvento('INS-FUNC', OrigemInformacaoItem::CADASTRO_FUNCIONAL, UnidadeLancamento::PERCENTUAL);
        $vantagem = $servidor->vantagensFuncionais()->create([
            'evento_id' => $eventoFuncional->id,
            'percentual' => 20,
            'data_inicio' => '2026-07-01',
            'referencia_documento' => 'Laudo 77/2026',
        ]);
        $vantagemEncerrada = $servidor->vantagensFuncionais()->create([
            'evento_id' => $eventoFuncional->id,
            'percentual' => 10,
            'data_inicio' => '2025-01-01',
            'data_fim' => '2026-06-30',
            'referencia_documento' => 'Laudo anterior',
        ]);

        $this->actingAs($usuario)->post(route('frequencia.store'), ['competencia_id' => $competencia->id])
            ->assertSessionHasNoErrors();

        $linha = FolhaFrequenciaServidor::firstOrFail();
        $this->assertSame($vinculoAtual->id, $linha->vinculo_funcional_id);
        $this->assertNotSame($vinculoAntigo->id, $linha->vinculo_funcional_id);
        $this->assertSame('Coordenador', $linha->cargo);
        $this->assertSame('COMISSIONADO', $linha->vinculo);
        $this->assertSame('DAS', $linha->designacoes_snapshot[0]['tipo']);
        $this->assertTrue($linha->mudanca_funcional_no_periodo);
        $this->assertDatabaseHas('folha_frequencia_itens', [
            'folha_frequencia_servidor_id' => $linha->id,
            'evento_id' => $eventoFuncional->id,
            'vantagem_funcional_id' => $vantagem->id,
            'percentual' => 20,
            'editavel_pelo_setor' => false,
            'referencia_documento' => 'Laudo 77/2026',
        ]);
        $this->assertDatabaseHas('folha_frequencia_itens', [
            'folha_frequencia_servidor_id' => $linha->id,
            'vantagem_funcional_id' => $vantagemEncerrada->id,
            'percentual' => 10,
            'vigencia_fim' => '2026-06-30',
            'editavel_pelo_setor' => false,
        ]);
    }

    public function test_sector_can_register_only_validated_and_authorized_monthly_item(): void
    {
        [$usuario, $setor, , $competencia] = $this->cenarioBase();
        [$folha, $linha] = $this->abrirFolha($usuario, $competencia);
        $evento = $this->criarEvento('INS-MES', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::PERCENTUAL, true);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);

        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $evento->id,
            'conteudo' => '20,5',
        ])->assertSessionHasErrors('referencia_documento', null, "item_{$linha->id}");

        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $evento->id,
            'conteudo' => '20,5',
            'referencia_documento' => 'Laudo mensal 8/2026',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('folha_frequencia_itens', [
            'folha_frequencia_servidor_id' => $linha->id,
            'evento_id' => $evento->id,
            'percentual' => 20.5,
            'editavel_pelo_setor' => true,
            'atualizado_por_id' => $usuario->id,
        ]);
        $this->actingAs($usuario)->get(route('frequencia.show', $folha))
            ->assertOk()
            ->assertSee('Itens da competência')
            ->assertSee('20,5%')
            ->assertSee('Laudo mensal 8/2026');

        $naoAutorizado = $this->criarEvento('PER-MES', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::PERCENTUAL, true);
        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $naoAutorizado->id,
            'conteudo' => '30',
            'referencia_documento' => 'Laudo 9/2026',
        ])->assertSessionHasErrors('evento_id', null, "item_{$linha->id}");

        $naoHomologado = $this->criarEvento('HC-PEND', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::HORAS);
        $naoHomologado->update(['regra_validada' => false]);
        $setor->eventosPermitidos()->attach($naoHomologado->id, ['ativo' => true]);
        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $naoHomologado->id,
            'conteudo' => '10',
        ])->assertSessionHasErrors('evento_id', null, "item_{$linha->id}");

        $this->assertDatabaseCount('folha_frequencia_itens', 1);
    }

    public function test_sector_can_update_and_remove_its_monthly_item_while_frequency_is_open(): void
    {
        [$usuario, $setor, , $competencia] = $this->cenarioBase();
        [$folha, $linha] = $this->abrirFolha($usuario, $competencia);
        $evento = $this->criarEvento('HC-EDIT', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::HORAS);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);
        $registro = $this->criarItemMensal($linha, $evento, $usuario);

        $this->actingAs($usuario)->put(route('frequencia.servidores.itens.update', [$folha, $linha, $registro]), [
            'conteudo' => '12,5',
            'observacao' => 'Carga mensal corrigida.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('folha_frequencia_itens', [
            'id' => $registro->id,
            'quantidade' => 12.5,
            'observacao' => 'Carga mensal corrigida.',
        ]);

        $this->actingAs($usuario)->delete(route('frequencia.servidores.itens.destroy', [$folha, $linha, $registro]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('folha_frequencia_itens', ['id' => $registro->id]);
    }

    public function test_monthly_item_respects_catalog_limits_and_rejects_duplicates(): void
    {
        [$usuario, $setor, , $competencia] = $this->cenarioBase();
        [$folha, $linha] = $this->abrirFolha($usuario, $competencia);
        $evento = $this->criarEvento('ADN-DIAS', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::DIAS, false, [
            'dias_maximo' => 5,
            'exige_observacao' => true,
        ]);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);

        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $evento->id,
            'conteudo' => '6',
            'observacao' => 'Plantões noturnos.',
        ])->assertSessionHasErrors('conteudo', null, "item_{$linha->id}");

        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $evento->id,
            'conteudo' => '4',
            'observacao' => 'Plantões noturnos.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($usuario)->post(route('frequencia.servidores.itens.store', [$folha, $linha]), [
            'evento_id' => $evento->id,
            'conteudo' => '3',
            'observacao' => 'Repetição indevida.',
        ])->assertSessionHasErrors('evento_id', null, "item_{$linha->id}");

        $this->assertDatabaseCount('folha_frequencia_itens', 1);
    }

    public function test_functional_snapshot_cannot_be_changed_or_removed_by_sector(): void
    {
        [$usuario, , , $competencia] = $this->cenarioBase();
        [$folha, $linha] = $this->abrirFolha($usuario, $competencia);
        $evento = $this->criarEvento('GRT-FUNC', OrigemInformacaoItem::CADASTRO_FUNCIONAL, UnidadeLancamento::MARCADOR);
        $registro = $linha->itens()->create([
            'evento_id' => $evento->id,
            'codigo_evento' => $evento->codigo_evento,
            'sigla' => $evento->sigla,
            'descricao' => $evento->descricao,
            'unidade_lancamento' => $evento->unidade_lancamento,
            'origem_informacao' => $evento->origem_informacao,
            'editavel_pelo_setor' => false,
        ]);

        $this->actingAs($usuario)->put(route('frequencia.servidores.itens.update', [$folha, $linha, $registro]), [
            'conteudo' => 'Tentativa de alteração',
        ])->assertSessionHasErrors('evento_id', null, "item_{$linha->id}");

        $this->actingAs($usuario)->delete(route('frequencia.servidores.itens.destroy', [$folha, $linha, $registro]))
            ->assertSessionHasErrors('vigencia', null, "item_{$linha->id}");

        $this->assertDatabaseHas('folha_frequencia_itens', ['id' => $registro->id, 'editavel_pelo_setor' => false]);
    }

    public function test_monthly_items_are_locked_after_submission_and_visible_to_central(): void
    {
        [$usuario, $setor, , $competencia] = $this->cenarioBase();
        [$folha, $linha] = $this->abrirFolha($usuario, $competencia);
        $evento = $this->criarEvento('HC-MES', OrigemInformacaoItem::SETOR_MENSAL, UnidadeLancamento::HORAS);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);
        $registro = $this->criarItemMensal($linha, $evento, $usuario);

        $folha->update(['status' => FolhaFrequenciaStatus::FINALIZADA, 'finalizado_por_id' => $usuario->id, 'finalizada_em' => now()]);
        $this->actingAs($usuario)->put(route('frequencia.servidores.itens.update', [$folha, $linha, $registro]), [
            'conteudo' => '12',
        ])->assertForbidden();

        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central-itens@example.test');
        $this->actingAs($central)->get(route('painel-frequencias.show', $folha))
            ->assertOk()
            ->assertSee('HC-MES')
            ->assertSee('10 hora(s)')
            ->assertSee('Informado pelo setor');
    }

    /** @return array{User, Setor, Servidor, Competencia} */
    private function cenarioBase(): array
    {
        $setor = Setor::create(['nome' => 'Setor de Frequência', 'sigla' => 'FREQ', 'ativo' => true]);
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial-itens@example.test');
        $servidor = Servidor::create([
            'matricula' => '30001',
            'nome' => 'Servidor dos Itens',
            'cargo' => 'Assistente',
            'vinculo' => VinculoServidor::EFETIVO,
            'carga_horaria' => 40,
            'setor_id' => $setor->id,
            'data_admissao' => '2020-01-01',
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'data_inicio' => '2026-06-11',
            'data_fim' => '2026-07-10',
            'status' => CompetenciaStatus::ABERTA,
            'data_limite' => '2026-07-31',
            'aberta_por' => $usuario->id,
        ]);

        return [$usuario, $setor, $servidor, $competencia];
    }

    /** @return array{FolhaFrequencia, FolhaFrequenciaServidor} */
    private function abrirFolha(User $usuario, Competencia $competencia): array
    {
        $folha = app(FolhaFrequenciaService::class)->criar($competencia, $usuario);

        return [$folha, $folha->servidores()->firstOrFail()];
    }

    private function criarEvento(
        string $codigo,
        OrigemInformacaoItem $origem,
        UnidadeLancamento $unidade,
        bool $documento = false,
        array $sobrescrever = [],
    ): EventoFolha {
        return EventoFolha::create(array_merge([
            'codigo_evento' => $codigo,
            'sigla' => $codigo,
            'descricao' => "Item {$codigo}",
            'tipo_evento' => TipoEvento::OUTROS,
            'unidade_lancamento' => $unidade,
            'origem_informacao' => $origem,
            'gera_efeito_financeiro' => true,
            'exige_documento' => $documento,
            'instrucoes_lancamento' => 'Regra homologada para teste do fluxo mensal nativo.',
            'regra_validada' => true,
            'ativo' => true,
        ], $sobrescrever));
    }

    private function criarItemMensal(FolhaFrequenciaServidor $linha, EventoFolha $evento, User $usuario): FolhaFrequenciaItem
    {
        return $linha->itens()->create([
            'evento_id' => $evento->id,
            'codigo_evento' => $evento->codigo_evento,
            'sigla' => $evento->sigla,
            'descricao' => $evento->descricao,
            'unidade_lancamento' => $evento->unidade_lancamento,
            'origem_informacao' => $evento->origem_informacao,
            'quantidade' => 10,
            'editavel_pelo_setor' => true,
            'atualizado_por_id' => $usuario->id,
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
