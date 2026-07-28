<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\ConferenciaServidorStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Enums\LancamentoStatus;
use App\Enums\OrigemInformacaoItem;
use App\Enums\ProjecaoExportacaoStatus;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\LancamentoSetorial;
use App\Models\ProjecaoExportacaoFolha;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\CompetenciaService;
use App\Services\ConferenciaLancamentoService;
use App\Services\FolhaFrequenciaService;
use App\Services\ProjecaoExportacaoFolhaService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class FolhaNativaExportacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_native_item_is_exported_once_with_traceability(): void
    {
        Storage::fake('local');
        [$folha, $item, $central] = $this->cenarioNativo(UnidadeLancamento::VALOR, true, true);
        $this->actingAs($central);

        $this->aprovarFolha($folha, $central);

        $projecao = ProjecaoExportacaoFolha::firstOrFail();
        $this->assertSame(ProjecaoExportacaoStatus::PRONTA, $projecao->status);
        $this->assertSame($item->id, $projecao->folha_frequencia_item_id);

        $resultado = app(ConferenciaLancamentoService::class)->exportar('2026-07');

        $this->assertSame(1, $resultado['quantidade']);
        $this->assertSame(0, $resultado['quantidadeLegada']);
        $this->assertSame(1, $resultado['quantidadeNativa']);
        $this->assertSame(
            str_pad('EVT001', 10, '0', STR_PAD_LEFT).
            str_pad('MAT001', 14, '0', STR_PAD_LEFT).
            str_pad('15000', 15, '0', STR_PAD_LEFT).PHP_EOL,
            Storage::disk('local')->get($resultado['caminhoArquivo'])
        );

        $projecao->refresh();
        $this->assertSame(ProjecaoExportacaoStatus::EXPORTADA, $projecao->status);
        $this->assertSame($resultado['exportacaoId'], $projecao->exportacao_id);
        $this->assertNotNull($projecao->exportado_em);

        app(ProjecaoExportacaoFolhaService::class)->projetar($folha->fresh());
        $this->assertDatabaseCount('projecoes_exportacao_folha', 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Nenhum lançamento conferido para exportação');
        app(ConferenciaLancamentoService::class)->exportar('2026-07');
    }

    public function test_mixed_export_contains_legacy_and_native_items_without_omission(): void
    {
        Storage::fake('local');
        [$folha, , $central, $servidor, , $setor] = $this->cenarioNativo(
            UnidadeLancamento::VALOR,
            true,
            true
        );
        $this->actingAs($central);
        $this->aprovarFolha($folha, $central);

        $eventoLegado = EventoFolha::create([
            'codigo_evento' => 'EVT002',
            'descricao' => 'Evento legado',
            'tipo_evento' => TipoEvento::OUTROS,
            'unidade_lancamento' => UnidadeLancamento::VALOR,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL,
            'gera_efeito_financeiro' => true,
            'regra_validada' => true,
            'ativo' => true,
        ]);
        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $eventoLegado->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $central->id,
            'competencia' => '2026-07',
            'valor' => 75,
        ]);
        $lancamento->forceFill(['status' => LancamentoStatus::CONFERIDO])->save();

        $resultado = app(ConferenciaLancamentoService::class)->exportar('2026-07');
        $conteudo = Storage::disk('local')->get($resultado['caminhoArquivo']);

        $this->assertSame(2, $resultado['quantidade']);
        $this->assertSame(1, $resultado['quantidadeLegada']);
        $this->assertSame(1, $resultado['quantidadeNativa']);
        $this->assertStringContainsString('0000EVT001', $conteudo);
        $this->assertStringContainsString('0000EVT002', $conteudo);
        $this->assertSame(LancamentoStatus::EXPORTADO, $lancamento->fresh()->status);
        $this->assertDatabaseHas('exportacao_lancamento', ['lancamento_id' => $lancamento->id]);
        $this->assertDatabaseHas('projecoes_exportacao_folha', [
            'folha_frequencia_id' => $folha->id,
            'status' => ProjecaoExportacaoStatus::EXPORTADA->value,
        ]);
    }

    public function test_approval_is_blocked_when_financial_unit_has_no_txt_conversion(): void
    {
        [$folha, , $central] = $this->cenarioNativo(UnidadeLancamento::PERCENTUAL, true, true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ainda sem conversão homologada para o TXT');

        try {
            $this->aprovarFolha($folha, $central);
        } finally {
            $this->assertSame(FolhaFrequenciaStatus::FINALIZADA, $folha->fresh()->status);
            $this->assertDatabaseCount('projecoes_exportacao_folha', 0);
        }
    }

    public function test_export_blocks_same_server_and_event_in_native_and_legacy_sources(): void
    {
        Storage::fake('local');
        [$folha, , $central, $servidor, $evento, $setor] = $this->cenarioNativo(
            UnidadeLancamento::VALOR,
            true,
            true
        );
        $this->actingAs($central);
        $this->aprovarFolha($folha, $central);

        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $central->id,
            'competencia' => '2026-07',
            'valor' => 150,
        ]);
        $lancamento->forceFill(['status' => LancamentoStatus::CONFERIDO])->save();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('itens duplicados entre a frequência nativa e os lançamentos legados');

        try {
            app(ConferenciaLancamentoService::class)->exportar('2026-07');
        } finally {
            $this->assertSame(LancamentoStatus::CONFERIDO, $lancamento->fresh()->status);
            $this->assertSame(
                ProjecaoExportacaoStatus::PRONTA,
                ProjecaoExportacaoFolha::firstOrFail()->status
            );
            $this->assertDatabaseCount('exportacoes_folha', 0);
        }
    }

    public function test_non_financial_native_item_does_not_create_export_projection(): void
    {
        [$folha, , $central] = $this->cenarioNativo(UnidadeLancamento::MARCADOR, false, true);

        $this->aprovarFolha($folha, $central);

        $this->assertSame(FolhaFrequenciaStatus::APROVADA, $folha->fresh()->status);
        $this->assertDatabaseCount('projecoes_exportacao_folha', 0);
    }

    public function test_closing_is_blocked_for_preexisting_approved_sheet_with_unprojectable_item(): void
    {
        [$folha, , , , , $setor, $competencia] = $this->cenarioNativo(
            UnidadeLancamento::PERCENTUAL,
            true,
            true
        );
        $folha->forceFill(['status' => FolhaFrequenciaStatus::APROVADA])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ainda sem conversão homologada para o TXT');

        try {
            app(CompetenciaService::class)->fechar($competencia);
        } finally {
            $this->assertSame(CompetenciaStatus::ABERTA, $competencia->fresh()->status);
            $this->assertDatabaseCount('projecoes_exportacao_folha', 0);
            $this->assertNotNull($setor->id);
        }
    }

    /**
     * @return array{
     *     FolhaFrequencia,
     *     FolhaFrequenciaItem,
     *     User,
     *     Servidor,
     *     EventoFolha,
     *     Setor,
     *     Competencia
     * }
     */
    private function cenarioNativo(
        UnidadeLancamento $unidade,
        bool $geraEfeitoFinanceiro,
        bool $regraValidada,
    ): array {
        $setor = Setor::create(['nome' => 'Setor de Teste', 'sigla' => 'TST', 'ativo' => true]);
        $setorial = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial-native@example.test');
        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central-native@example.test');
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $central->id,
        ]);
        $servidor = Servidor::create([
            'matricula' => 'MAT001',
            'nome' => 'Servidor nativo',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $evento = EventoFolha::create([
            'codigo_evento' => 'EVT001',
            'descricao' => 'Evento nativo',
            'tipo_evento' => TipoEvento::OUTROS,
            'unidade_lancamento' => $unidade,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL,
            'gera_efeito_financeiro' => $geraEfeitoFinanceiro,
            'regra_validada' => $regraValidada,
            'ativo' => true,
        ]);
        $folha = FolhaFrequencia::create([
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'status' => FolhaFrequenciaStatus::FINALIZADA,
            'rodada_conferencia' => 1,
            'criado_por_id' => $setorial->id,
            'finalizado_por_id' => $setorial->id,
            'finalizada_em' => now(),
        ]);
        $servidorFolha = $folha->servidores()->create([
            'servidor_id' => $servidor->id,
            'matricula' => $servidor->matricula,
            'nome' => $servidor->nome,
            'status' => FrequenciaServidorStatus::INTEGRAL,
            'atualizado_por_id' => $setorial->id,
            'preenchida_em' => now(),
        ]);
        $item = $servidorFolha->itens()->create([
            'evento_id' => $evento->id,
            'codigo_evento' => $evento->codigo_evento,
            'descricao' => $evento->descricao,
            'unidade_lancamento' => $unidade,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL,
            'gera_efeito_financeiro' => $geraEfeitoFinanceiro,
            'regra_validada_snapshot' => $regraValidada,
            'valor' => $unidade === UnidadeLancamento::VALOR ? 150 : null,
            'percentual' => $unidade === UnidadeLancamento::PERCENTUAL ? 20 : null,
            'editavel_pelo_setor' => true,
            'atualizado_por_id' => $setorial->id,
        ]);

        return [$folha, $item, $central, $servidor, $evento, $setor, $competencia];
    }

    private function aprovarFolha(FolhaFrequencia $folha, User $central): void
    {
        $servidorFolha = $folha->servidores()->firstOrFail();
        $service = app(FolhaFrequenciaService::class);
        $service->conferirServidor(
            $folha,
            $servidorFolha,
            ConferenciaServidorStatus::CONFERIDO,
            null,
            $central,
        );
        $service->aprovar($folha, $central);
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
