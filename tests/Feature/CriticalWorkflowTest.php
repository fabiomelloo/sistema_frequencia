<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Enums\ImportacaoFinalidade;
use App\Enums\ImportacaoLinhaStatus;
use App\Enums\ImportacaoStatus;
use App\Enums\LancamentoStatus;
use App\Enums\TipoEvento;
use App\Enums\TipoOcorrenciaFrequencia;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\FolhaFrequencia;
use App\Models\Importacao;
use App\Models\LancamentoSetorial;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\ImportacaoOcorrenciaService;
use App\Services\ImportacaoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CriticalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Os testes abaixo preservam somente a verificacao do legado historico.
        config(['operations.legacy.spreadsheet_import_enabled' => true]);
    }

    public function test_policies_preserve_the_current_role_matrix(): void
    {
        $setor = $this->criarSetor();
        $admin = $this->criarUsuario($setor, UserRole::ADMIN, 'admin@example.test');
        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central@example.test');

        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($central)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($central)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Servidor::class));
        $this->assertTrue(Gate::forUser($central)->allows('create', Servidor::class));
    }

    public function test_permission_destroy_route_uses_sector_and_event_parameters(): void
    {
        $setor = $this->criarSetor();
        $evento = $this->criarEvento();

        $url = route('admin.permissoes.destroy', [
            'setor' => $setor,
            'evento' => $evento,
        ]);

        $this->assertStringEndsWith("/admin/permissoes/{$setor->id}/{$evento->id}", $url);
    }

    public function test_setorial_user_can_open_an_editable_entry(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial@example.test');
        $evento = $this->criarEvento();
        $servidor = $this->criarServidor($setor);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);

        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $usuario->id,
            'competencia' => '2026-07',
            'dias_trabalhados' => 1,
        ]);
        $lancamento->status = LancamentoStatus::PENDENTE;
        $lancamento->save();

        $this->actingAs($usuario)
            ->get(route('lancamentos.edit', $lancamento))
            ->assertOk();
    }

    public function test_database_rejects_two_active_entries_for_the_same_business_key(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'unique-entry@example.test');
        $evento = $this->criarEvento();
        $servidor = $this->criarServidor($setor);
        $attributes = [
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $usuario->id,
            'competencia' => '2026-07',
            'dias_trabalhados' => 1,
        ];

        LancamentoSetorial::create($attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        LancamentoSetorial::create($attributes);
    }

    public function test_inactive_or_deleted_entry_does_not_reserve_the_business_key(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'released-entry@example.test');
        $evento = $this->criarEvento();
        $servidor = $this->criarServidor($setor);
        $attributes = [
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $usuario->id,
            'competencia' => '2026-07',
            'dias_trabalhados' => 1,
        ];

        $cancelado = LancamentoSetorial::create($attributes);
        $cancelado->forceFill(['status' => LancamentoStatus::CANCELADO])->save();
        LancamentoSetorial::create($attributes);

        LancamentoSetorial::query()->where('status', LancamentoStatus::PENDENTE)->firstOrFail()->delete();
        $replacement = LancamentoSetorial::create($attributes);

        $this->assertNotNull($replacement->id);
        $this->assertDatabaseCount('lancamentos_setoriais', 3);
    }

    public function test_csv_import_records_the_authenticated_author(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'importador@example.test');
        $evento = $this->criarEvento();
        $servidor = $this->criarServidor($setor);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);

        Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $arquivo = tempnam(sys_get_temp_dir(), 'frequencia_csv_');
        file_put_contents($arquivo, "matricula;codigo_evento;competencia;dias_trabalhados\nMAT001;EVT001;2026-07;1\n");

        try {
            $this->actingAs($usuario);
            $resultado = app(ImportacaoService::class)->importarCsv($arquivo, $setor->id, $usuario);
        } finally {
            @unlink($arquivo);
        }

        $this->assertSame(1, $resultado['importados']);
        $this->assertDatabaseHas('lancamentos_setoriais', [
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'criado_por_id' => $usuario->id,
            'status' => LancamentoStatus::PENDENTE->value,
        ]);
    }

    public function test_competencia_uses_the_payroll_period_from_day_11_to_day_10(): void
    {
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
        ]);

        $this->assertSame('2026-06-11', $competencia->data_inicio->format('Y-m-d'));
        $this->assertSame('2026-07-10', $competencia->data_fim->format('Y-m-d'));
        $this->assertSame(30, $competencia->diasNoPeriodo());
        $this->assertStringContainsString('11/06/2026 a 10/07/2026', $competencia->descricao);
    }

    public function test_setor_hierarchy_rejects_cycles(): void
    {
        $raiz = $this->criarSetor();
        $filho = Setor::create([
            'nome' => 'Setor subordinado',
            'sigla' => 'SUB',
            'codigo_externo' => '001.303',
            'setor_pai_id' => $raiz->id,
            'ativo' => true,
        ]);
        $admin = $this->criarUsuario($raiz, UserRole::ADMIN, 'hierarquia@example.test');

        $this->assertTrue($filho->setorPai->is($raiz));

        $this->actingAs($admin)
            ->put(route('admin.setores.update', $raiz), [
                'nome' => $raiz->nome,
                'sigla' => $raiz->sigla,
                'setor_pai_id' => $filho->id,
                'ativo' => '1',
            ])
            ->assertSessionHasErrors('setor_pai_id');
    }

    public function test_staged_import_only_creates_entries_after_confirmation(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'lote@example.test');
        $evento = $this->criarEvento();
        $this->criarServidor($setor);
        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);
        Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $arquivo = UploadedFile::fake()->createWithContent(
            'frequencia.csv',
            "matricula;codigo_evento;competencia;dias_trabalhados\nMAT001;EVT001;2026-07;1\n",
        );

        $service = app(ImportacaoService::class);
        $lote = $service->criarLote($arquivo, $setor->id, $usuario);

        $this->assertSame(ImportacaoStatus::PENDENTE, $lote->status);
        $this->assertSame(1, $lote->linhas_validas);
        $this->assertDatabaseCount('lancamentos_setoriais', 0);

        $resultado = $service->confirmarLote($lote, $usuario);
        $lote->refresh();
        $linha = $lote->linhas()->firstOrFail();

        $this->assertSame(1, $resultado['importados']);
        $this->assertSame(ImportacaoStatus::PROCESSADA, $lote->status);
        $this->assertSame(ImportacaoLinhaStatus::PROCESSADA, $linha->status);
        $this->assertNotNull($linha->lancamento_id);
        $this->assertDatabaseCount('lancamentos_setoriais', 1);
    }

    public function test_invalid_staged_import_cannot_be_confirmed(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'lote-invalido@example.test');
        $arquivo = UploadedFile::fake()->createWithContent(
            'invalido.csv',
            "matricula;codigo_evento;competencia;dias_trabalhados\nDESCONHECIDA;INEXISTENTE;2026-07;1\n",
        );

        $service = app(ImportacaoService::class);
        $lote = $service->criarLote($arquivo, $setor->id, $usuario);

        $this->assertSame(ImportacaoStatus::INVALIDA, $lote->status);
        $this->assertSame(1, $lote->linhas_invalidas);
        $this->expectException(\InvalidArgumentException::class);

        $service->confirmarLote($lote, $usuario);
    }

    public function test_import_routes_are_not_available_to_operational_users(): void
    {
        $this->assertFalse(app('router')->has('lancamentos.importar.form'));
        $this->assertFalse(app('router')->has('lancamentos.importar'));
        $this->assertFalse(app('router')->has('ocorrencias.importar.form'));
        $this->assertFalse(app('router')->has('ocorrencias.importar.store'));
    }

    public function test_setorial_user_can_record_structured_occurrence_without_financial_entry(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'ocorrencia@example.test');
        $servidor = $this->criarServidor($setor);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $this->actingAs($usuario)
            ->post(route('ocorrencias.store'), [
                'servidor_id' => $servidor->id,
                'competencia_id' => $competencia->id,
                'tipo' => TipoOcorrenciaFrequencia::ATESTADO_MEDICO->value,
                'dias_especificos' => ['2026-06-19', '2026-06-25'],
                'justificada' => '1',
                'possui_comprovacao' => '1',
                'referencia_documento' => 'Atestado entregue ao RH',
                'observacao_original' => 'Dias 19 e 25 - atestado médico.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ocorrencias_frequencia', [
            'servidor_id' => $servidor->id,
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'tipo' => TipoOcorrenciaFrequencia::ATESTADO_MEDICO->value,
            'criado_por_id' => $usuario->id,
        ]);
        $this->assertDatabaseCount('ocorrencia_dias', 2);
        $this->assertDatabaseCount('lancamentos_setoriais', 0);
    }

    public function test_occurrence_rejects_dates_outside_the_competence_period(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'periodo-ocorrencia@example.test');
        $servidor = $this->criarServidor($setor);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $this->actingAs($usuario)
            ->from(route('ocorrencias.create'))
            ->post(route('ocorrencias.store'), [
                'servidor_id' => $servidor->id,
                'competencia_id' => $competencia->id,
                'tipo' => TipoOcorrenciaFrequencia::FALTA->value,
                'dias_especificos' => ['2026-07-11'],
                'possui_comprovacao' => '0',
            ])
            ->assertRedirect(route('ocorrencias.create'))
            ->assertSessionHasErrors('data_inicio');

        $this->assertDatabaseCount('ocorrencias_frequencia', 0);
    }

    public function test_occurrence_from_another_sector_is_not_accessible(): void
    {
        $setor = $this->criarSetor();
        $outroSetor = Setor::create(['nome' => 'Outro setor', 'sigla' => 'OUT', 'ativo' => true]);
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'setor-a@example.test');
        $outroUsuario = $this->criarUsuario($outroSetor, UserRole::SETORIAL, 'setor-b@example.test');
        $servidor = $this->criarServidor($setor);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $ocorrencia = OcorrenciaFrequencia::create([
            'servidor_id' => $servidor->id,
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'tipo' => TipoOcorrenciaFrequencia::FALTA,
            'possui_comprovacao' => false,
            'origem' => 'MANUAL',
            'criado_por_id' => $usuario->id,
        ]);

        $this->actingAs($outroUsuario)
            ->get(route('ocorrencias.show', $ocorrencia))
            ->assertForbidden();
    }

    public function test_staged_occurrence_import_only_persists_after_confirmation(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'importa-ocorrencia@example.test');
        $servidor = $this->criarServidor($setor);
        Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);
        $conteudo = "matricula;competencia;tipo;data_inicio;data_fim;dias_especificos;justificada;possui_comprovacao;referencia_documento;observacao_original\n"
            ."{$servidor->matricula};2026-07;ATESTADO_MEDICO;;;2026-06-19,2026-06-25;SIM;SIM;Atestado entregue;Dias 19 e 25\n";
        $arquivo = UploadedFile::fake()->createWithContent('ocorrencias.csv', $conteudo);

        $service = app(ImportacaoOcorrenciaService::class);
        $lote = $service->criarLote($arquivo, $setor->id, $usuario);

        $this->assertSame(ImportacaoFinalidade::OCORRENCIAS, $lote->finalidade);
        $this->assertSame(ImportacaoStatus::PENDENTE, $lote->status);
        $this->assertDatabaseCount('ocorrencias_frequencia', 0);
        $this->assertDatabaseCount('lancamentos_setoriais', 0);

        $resultado = $service->confirmarLote($lote, $usuario);
        $linha = $lote->linhas()->firstOrFail();

        $this->assertSame(1, $resultado['importados']);
        $this->assertNotNull($linha->ocorrencia_id);
        $this->assertDatabaseHas('ocorrencias_frequencia', [
            'id' => $linha->ocorrencia_id,
            'importacao_linha_id' => $linha->id,
            'origem' => 'CSV',
        ]);
        $this->assertDatabaseCount('ocorrencia_dias', 2);
        $this->assertDatabaseCount('lancamentos_setoriais', 0);
    }

    public function test_occurrence_import_blocks_invalid_period_without_partial_persistence(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'ocorrencia-invalida@example.test');
        $servidor = $this->criarServidor($setor);
        Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);
        $conteudo = "matricula;competencia;tipo;data_inicio;data_fim;dias_especificos;justificada;possui_comprovacao;referencia_documento;observacao_original\n"
            ."{$servidor->matricula};2026-07;FALTA;;;2026-07-11;NAO;NAO;;Fora do período\n";
        $arquivo = UploadedFile::fake()->createWithContent('ocorrencias-invalidas.csv', $conteudo);

        $lote = app(ImportacaoOcorrenciaService::class)->criarLote($arquivo, $setor->id, $usuario);

        $this->assertSame(ImportacaoStatus::INVALIDA, $lote->status);
        $this->assertSame(1, $lote->linhas_invalidas);
        $this->assertDatabaseCount('ocorrencias_frequencia', 0);
        $this->assertFalse($lote->podeProcessar());
    }

    public function test_financial_importer_rejects_an_occurrence_batch(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'separacao-lotes@example.test');
        $lote = Importacao::create([
            'setor_id' => $setor->id,
            'usuario_id' => $usuario->id,
            'nome_original' => 'ocorrencias.csv',
            'caminho_arquivo' => 'importacoes/inexistente.csv',
            'hash_arquivo' => str_repeat('b', 64),
            'finalidade' => ImportacaoFinalidade::OCORRENCIAS,
            'status' => ImportacaoStatus::PENDENTE,
            'linhas_validas' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('não pertence à importação de lançamentos');
        app(ImportacaoService::class)->confirmarLote($lote, $usuario);
    }

    public function test_native_monthly_frequency_creates_a_period_snapshot_without_financial_entries(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'frequencia-mensal@example.test');
        $servidor = $this->criarServidor($setor);
        $servidor->update(['cargo' => 'Agente Administrativo', 'carga_horaria' => 40]);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);

        $this->actingAs($usuario)
            ->post(route('frequencia.store'), ['competencia_id' => $competencia->id])
            ->assertRedirect();

        $folha = FolhaFrequencia::firstOrFail();
        $this->assertSame(FolhaFrequenciaStatus::RASCUNHO, $folha->status);
        $this->assertDatabaseHas('folha_frequencia_servidores', [
            'folha_frequencia_id' => $folha->id,
            'servidor_id' => $servidor->id,
            'matricula' => $servidor->matricula,
            'cargo' => 'Agente Administrativo',
            'carga_horaria' => 40,
            'status' => FrequenciaServidorStatus::PENDENTE->value,
        ]);
        $this->assertDatabaseCount('lancamentos_setoriais', 0);
    }

    public function test_frequency_with_absences_requires_registered_days(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'faltas-frequencia@example.test');
        $servidor = $this->criarServidor($setor);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);
        $this->actingAs($usuario)->post(route('frequencia.store'), ['competencia_id' => $competencia->id]);
        $folha = FolhaFrequencia::firstOrFail();
        $item = $folha->servidores()->firstOrFail();

        $this->actingAs($usuario)
            ->put(route('frequencia.servidores.update', [$folha, $item]), [
                'status' => FrequenciaServidorStatus::COM_FALTAS->value,
            ])
            ->assertSessionHasErrors("servidor_{$item->id}");

        $ocorrencia = OcorrenciaFrequencia::create([
            'servidor_id' => $servidor->id,
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'tipo' => TipoOcorrenciaFrequencia::FALTA,
            'possui_comprovacao' => false,
            'origem' => 'MANUAL',
            'criado_por_id' => $usuario->id,
        ]);
        $ocorrencia->dias()->create(['data' => '2026-06-19']);

        $this->actingAs($usuario)
            ->put(route('frequencia.servidores.update', [$folha, $item]), [
                'status' => FrequenciaServidorStatus::COM_FALTAS->value,
                'observacao_geral' => 'Falta registrada no sistema.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('folha_frequencia_servidores', [
            'id' => $item->id,
            'status' => FrequenciaServidorStatus::COM_FALTAS->value,
        ]);
    }

    public function test_monthly_frequency_only_finalizes_after_every_server_is_filled(): void
    {
        $setor = $this->criarSetor();
        $usuario = $this->criarUsuario($setor, UserRole::SETORIAL, 'finaliza-frequencia@example.test');
        $this->criarServidor($setor);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $usuario->id,
        ]);
        $this->actingAs($usuario)->post(route('frequencia.store'), ['competencia_id' => $competencia->id]);
        $folha = FolhaFrequencia::firstOrFail();
        $item = $folha->servidores()->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasErrors('finalizacao');

        $this->actingAs($usuario)
            ->put(route('frequencia.servidores.update', [$folha, $item]), [
                'status' => FrequenciaServidorStatus::INTEGRAL->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($usuario)
            ->post(route('frequencia.finalizar', $folha))
            ->assertSessionHasNoErrors();

        $this->assertSame(FolhaFrequenciaStatus::FINALIZADA, $folha->fresh()->status);
        $this->assertNotNull($folha->fresh()->finalizada_em);
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
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => $role,
        ]);
    }

    private function criarEvento(): EventoFolha
    {
        return EventoFolha::create([
            'codigo_evento' => 'EVT001',
            'descricao' => 'Evento de teste',
            'tipo_evento' => TipoEvento::OUTROS,
            'exige_dias' => false,
            'exige_valor' => false,
            'exige_observacao' => false,
            'exige_porcentagem' => false,
            'ativo' => true,
        ]);
    }

    private function criarServidor(Setor $setor): Servidor
    {
        return Servidor::create([
            'matricula' => 'MAT001',
            'nome' => 'Servidor de teste',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
    }
}
