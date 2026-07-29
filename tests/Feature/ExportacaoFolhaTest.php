<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Enums\TipoEvento;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\GeradorTxtFolhaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportacaoFolhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_is_written_to_private_storage_with_integrity_metadata(): void
    {
        Storage::fake('local');
        [$usuario, $lancamento] = $this->cenarioExportacao();
        $this->actingAs($usuario);

        $resultado = DB::transaction(
            fn (): array => app(GeradorTxtFolhaService::class)->gerar('2026-07')
        );

        $this->assertStringStartsWith('exportacoes/', $resultado['caminhoArquivo']);
        Storage::disk('local')->assertExists($resultado['caminhoArquivo']);

        $conteudo = Storage::disk('local')->get($resultado['caminhoArquivo']);
        $this->assertDatabaseHas('exportacoes_folha', [
            'nome_arquivo' => $resultado['nomeArquivo'],
            'hash_arquivo' => hash('sha256', $conteudo),
            'usuario_id' => $usuario->id,
            'quantidade_lancamentos' => 1,
        ]);
        $this->assertDatabaseHas('exportacao_lancamento', [
            'lancamento_id' => $lancamento->id,
        ]);
    }

    public function test_cleanup_only_removes_expired_private_export_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exportacoes/antigo.txt', 'antigo');
        Storage::disk('local')->put('exportacoes/recente.txt', 'recente');
        Storage::disk('local')->put('evidencias/preservar.txt', 'evidencia');
        touch(Storage::disk('local')->path('exportacoes/antigo.txt'), now()->subDays(8)->timestamp);

        $this->artisan('sistema:limpar-exportacoes --dias=7')->assertSuccessful();

        Storage::disk('local')->assertMissing('exportacoes/antigo.txt');
        Storage::disk('local')->assertExists('exportacoes/recente.txt');
        Storage::disk('local')->assertExists('evidencias/preservar.txt');
    }

    public function test_export_accepts_closed_competencia_after_institutional_closing(): void
    {
        Storage::fake('local');
        [$usuario, $lancamento] = $this->cenarioExportacao(CompetenciaStatus::FECHADA);
        $this->actingAs($usuario);

        $response = $this->post(route('painel.exportar'), [
            'competencia' => '2026-07',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('lancamentos_setoriais', [
            'id' => $lancamento->id,
            'status' => LancamentoStatus::EXPORTADO->value,
        ]);
        $this->assertDatabaseHas('exportacao_lancamento', [
            'lancamento_id' => $lancamento->id,
        ]);
    }

    /** @return array{User, LancamentoSetorial} */
    private function cenarioExportacao(CompetenciaStatus $status = CompetenciaStatus::ABERTA): array
    {
        $setor = Setor::create(['nome' => 'Setor de Teste', 'sigla' => 'TST', 'ativo' => true]);
        $usuario = User::create([
            'name' => 'Central',
            'email' => 'central-exportacao@example.test',
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => UserRole::CENTRAL,
        ]);
        Competencia::create([
            'referencia' => '2026-07',
            'status' => $status,
            'aberta_por' => $usuario->id,
            'fechada_por' => $status === CompetenciaStatus::FECHADA ? $usuario->id : null,
            'fechada_em' => $status === CompetenciaStatus::FECHADA ? now() : null,
        ]);
        $servidor = Servidor::create([
            'matricula' => 'MAT001',
            'nome' => 'Servidor de teste',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $evento = EventoFolha::create([
            'codigo_evento' => 'EVT001',
            'descricao' => 'Evento de teste',
            'tipo_evento' => TipoEvento::OUTROS,
            'exige_dias' => false,
            'exige_valor' => false,
            'exige_observacao' => false,
            'exige_porcentagem' => false,
            'ativo' => true,
        ]);
        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'criado_por_id' => $usuario->id,
            'competencia' => '2026-07',
            'valor' => 150,
        ]);
        $lancamento->status = LancamentoStatus::CONFERIDO;
        $lancamento->save();

        return [$usuario, $lancamento];
    }
}
