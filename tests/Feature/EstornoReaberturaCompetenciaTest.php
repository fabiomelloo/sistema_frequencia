<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\CompetenciaService;
use App\Services\EstornoLancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EstornoReaberturaCompetenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_estorno_is_completed_while_closed_before_reopening(): void
    {
        [$competencia, $lancamento, $setorial, $central] = $this->cenarioFechadoExportado();
        $competencias = app(CompetenciaService::class);
        $estornos = app(EstornoLancamentoService::class);

        $this->actingAs($central);
        try {
            $competencias->abrir($competencia->referencia);
            $this->fail('A reabertura deveria ser bloqueada enquanto há lançamento exportado.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Conclua os estornos', $e->getMessage());
        }

        $this->actingAs($setorial);
        $estornos->solicitar($lancamento, 'Valor exportado incorretamente');
        $this->assertSame(LancamentoStatus::ESTORNO_SOLICITADO, $lancamento->fresh()->status);

        $this->actingAs($central);
        try {
            $competencias->abrir($competencia->referencia);
            $this->fail('A reabertura deveria ser bloqueada enquanto o estorno está pendente.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('1 solicitação(ões) de estorno pendente(s)', $e->getMessage());
        }

        $estornos->aprovar($lancamento->fresh());
        $this->assertSame(LancamentoStatus::ESTORNADO, $lancamento->fresh()->status);

        $competencias->abrir($competencia->referencia);
        $this->assertSame(CompetenciaStatus::ABERTA, $competencia->fresh()->status);
    }

    public function test_central_can_refuse_request_and_restore_exported_status(): void
    {
        [, $lancamento, $setorial, $central] = $this->cenarioFechadoExportado();
        $estornos = app(EstornoLancamentoService::class);

        $this->actingAs($setorial);
        $estornos->solicitar($lancamento, 'O lançamento precisa ser revisto');

        $this->actingAs($central);
        $estornos->recusar($lancamento->fresh(), 'A exportação está correta');

        $this->assertSame(LancamentoStatus::EXPORTADO, $lancamento->fresh()->status);
        $this->assertNull($lancamento->fresh()->motivo_estorno);
    }

    public function test_exported_entry_cannot_be_reversed_without_request(): void
    {
        [, $lancamento, , $central] = $this->cenarioFechadoExportado();
        $this->actingAs($central);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Apenas lançamentos com ESTORNO SOLICITADO podem ser estornados.');

        app(EstornoLancamentoService::class)->aprovar($lancamento, 'Tentativa direta');
    }

    public function test_terminal_reversal_does_not_block_closing_competence(): void
    {
        [$competencia, $lancamento, $setorial, $central] = $this->cenarioFechadoExportado();
        $estornos = app(EstornoLancamentoService::class);

        $this->actingAs($setorial);
        $estornos->solicitar($lancamento, 'Lançamento exportado em duplicidade');
        $this->actingAs($central);
        $estornos->aprovar($lancamento->fresh());

        $lancamento->servidor->forceFill([
            'ativo' => false,
            'data_desligamento' => '2098-01-01',
        ])->save();
        $competencia->forceFill(['status' => CompetenciaStatus::ABERTA])->save();
        app(CompetenciaService::class)->fechar($competencia->fresh());

        $this->assertSame(CompetenciaStatus::FECHADA, $competencia->fresh()->status);
    }

    /** @return array{Competencia, LancamentoSetorial, User, User} */
    private function cenarioFechadoExportado(): array
    {
        $setor = Setor::create(['nome' => 'Recursos Humanos', 'sigla' => 'RH', 'ativo' => true]);
        $setorial = User::create([
            'name' => 'Setorial',
            'email' => 'setorial@example.test',
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => UserRole::SETORIAL,
        ]);
        $central = User::create([
            'name' => 'Central',
            'email' => 'central@example.test',
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => UserRole::CENTRAL,
        ]);
        $servidor = Servidor::create([
            'matricula' => 'EST-001',
            'nome' => 'Servidor de teste',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $evento = EventoFolha::create([
            'codigo_evento' => 'EST001',
            'descricao' => 'Evento de teste',
            'tipo_evento' => 'OUTROS',
            'exige_dias' => false,
            'exige_valor' => false,
            'exige_observacao' => false,
            'ativo' => true,
        ]);
        $competencia = Competencia::create([
            'referencia' => '2099-01',
            'status' => CompetenciaStatus::FECHADA,
            'aberta_por' => $central->id,
            'fechada_por' => $central->id,
            'fechada_em' => now(),
        ]);
        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'competencia' => $competencia->referencia,
        ]);
        $lancamento->forceFill([
            'status' => LancamentoStatus::EXPORTADO,
            'exportado_em' => now(),
        ])->save();

        return [$competencia, $lancamento, $setorial, $central];
    }
}
