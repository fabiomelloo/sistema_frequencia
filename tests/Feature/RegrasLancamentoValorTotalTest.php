<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Enums\TipoEvento;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\Setor;
use App\Services\RegrasLancamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegrasLancamentoValorTotalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-15 12:00:00');
        Configuracao::set('limite_valor_total_servidor', '1000.00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_gratificacoes_sucessivas_sao_contadas_no_limite_acumulado(): void
    {
        [$servidor, $setor] = $this->criarContexto();
        $eventoExistente = $this->criarEvento('GRAT001');
        $eventoAtual = $this->criarEvento('GRAT002');

        $this->criarLancamento(
            $servidor,
            $setor,
            $eventoExistente,
            valor: null,
            valorGratificacao: 600
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('seria de R$ 1.200,00');

        app(RegrasLancamentoService::class)->validar(
            $servidor,
            $eventoAtual,
            [
                'competencia' => '2026-07',
                'valor_gratificacao' => 600,
            ]
        );
    }

    public function test_valor_zero_nao_oculta_a_gratificacao_atual(): void
    {
        [$servidor, $setor] = $this->criarContexto();
        $eventoExistente = $this->criarEvento('VALOR001', TipoEvento::OUTROS);
        $eventoAtual = $this->criarEvento('GRAT003');

        $this->criarLancamento(
            $servidor,
            $setor,
            $eventoExistente,
            valor: 500,
            valorGratificacao: null
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('seria de R$ 1.100,00');

        app(RegrasLancamentoService::class)->validar(
            $servidor,
            $eventoAtual,
            [
                'competencia' => '2026-07',
                'valor' => 0,
                'valor_gratificacao' => 600,
            ]
        );
    }

    public function test_soma_exatamente_no_teto_e_aceita(): void
    {
        [$servidor, $setor] = $this->criarContexto();
        $eventoExistente = $this->criarEvento('GRAT004');
        $eventoAtual = $this->criarEvento('GRAT005');

        $this->criarLancamento(
            $servidor,
            $setor,
            $eventoExistente,
            valor: null,
            valorGratificacao: 600
        );

        app(RegrasLancamentoService::class)->validar(
            $servidor,
            $eventoAtual,
            [
                'competencia' => '2026-07',
                'valor_gratificacao' => 400,
            ]
        );

        $this->addToAssertionCount(1);
    }

    /** @return array{Servidor, Setor} */
    private function criarContexto(): array
    {
        $setor = Setor::create([
            'nome' => 'Setor de Teste',
            'sigla' => 'TST',
            'ativo' => true,
        ]);

        $servidor = Servidor::create([
            'matricula' => 'MAT001',
            'nome' => 'Servidor de teste',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);

        Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
        ]);

        return [$servidor, $setor];
    }

    private function criarEvento(
        string $codigo,
        TipoEvento $tipo = TipoEvento::GRATIFICACAO
    ): EventoFolha {
        return EventoFolha::create([
            'codigo_evento' => $codigo,
            'descricao' => "Evento {$codigo}",
            'tipo_evento' => $tipo,
            'exige_dias' => false,
            'exige_valor' => false,
            'exige_observacao' => false,
            'exige_porcentagem' => false,
            'ativo' => true,
        ]);
    }

    private function criarLancamento(
        Servidor $servidor,
        Setor $setor,
        EventoFolha $evento,
        ?float $valor,
        ?float $valorGratificacao
    ): LancamentoSetorial {
        $lancamento = LancamentoSetorial::create([
            'servidor_id' => $servidor->id,
            'evento_id' => $evento->id,
            'setor_origem_id' => $setor->id,
            'competencia' => '2026-07',
            'valor' => $valor,
            'valor_gratificacao' => $valorGratificacao,
        ]);

        $lancamento->forceFill(['status' => LancamentoStatus::PENDENTE])->save();

        return $lancamento;
    }
}
