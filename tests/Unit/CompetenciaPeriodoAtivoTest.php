<?php

namespace Tests\Unit;

use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Services\RegrasLancamentoService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CompetenciaPeriodoAtivoTest extends TestCase
{
    #[DataProvider('bordasDoPeriodo')]
    public function test_calcula_dias_uteis_no_periodo_ativo_do_servidor(
        ?string $admissao,
        ?string $desligamento,
        string $inicioEsperado,
        string $fimEsperado,
        int $diasUteisEsperados
    ): void {
        $competencia = $this->competencia();
        $servidor = $this->servidor($admissao, $desligamento);

        [$inicio, $fim] = $competencia->periodoAtivoDoServidor($servidor);

        $this->assertSame($inicioEsperado, $inicio->toDateString());
        $this->assertSame($fimEsperado, $fim->toDateString());
        $this->assertSame(
            $diasUteisEsperados,
            Competencia::contarDiasUteis($inicio, $fim)
        );
    }

    public static function bordasDoPeriodo(): array
    {
        return [
            'periodo integral' => [null, null, '2026-06-11', '2026-07-10', 22],
            'admissao no mes anterior' => ['2026-06-20', null, '2026-06-20', '2026-07-10', 15],
            'admissao no mes de referencia' => ['2026-07-05', null, '2026-07-05', '2026-07-10', 5],
            'desligamento no mes anterior' => [null, '2026-06-25', '2026-06-11', '2026-06-25', 11],
            'desligamento depois do periodo' => [null, '2026-07-20', '2026-06-11', '2026-07-10', 22],
        ];
    }

    public function test_respeita_periodo_customizado_e_feriados(): void
    {
        $competencia = $this->competencia('2026-06-15', '2026-07-15');
        $servidor = $this->servidor('2026-07-01', null);

        [$inicio, $fim] = $competencia->periodoAtivoDoServidor($servidor);

        $this->assertSame('2026-07-01', $inicio->toDateString());
        $this->assertSame('2026-07-15', $fim->toDateString());
        $this->assertSame(
            10,
            Competencia::contarDiasUteis($inicio, $fim, ['2026-07-09'])
        );
    }

    public function test_retorna_nulo_quando_vinculo_nao_intersecta_o_periodo(): void
    {
        $competencia = $this->competencia();

        $this->assertNull(
            $competencia->periodoAtivoDoServidor($this->servidor('2026-07-11', null))
        );
        $this->assertNull(
            $competencia->periodoAtivoDoServidor($this->servidor(null, '2026-06-10'))
        );
    }

    public function test_validacao_individual_usa_o_teto_de_dias_uteis_ativos(): void
    {
        $evento = new EventoFolha;
        $evento->setRawAttributes([
            'exige_dias' => true,
            'dias_maximo' => null,
        ]);
        $metodo = new ReflectionMethod(RegrasLancamentoService::class, 'validarDias');
        $service = new RegrasLancamentoService;

        $metodo->invoke($service, $evento, ['dias_trabalhados' => 15], $this->competencia(), 15);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'período da competência de 11/06/2026 a 10/07/2026 (15 dias úteis)'
        );

        $metodo->invoke($service, $evento, ['dias_trabalhados' => 16], $this->competencia(), 15);
    }

    private function competencia(
        string $inicio = '2026-06-11',
        string $fim = '2026-07-10'
    ): Competencia {
        $competencia = new Competencia;
        $competencia->setRawAttributes([
            'referencia' => '2026-07',
            'data_inicio' => $inicio,
            'data_fim' => $fim,
        ]);

        return $competencia;
    }

    private function servidor(?string $admissao, ?string $desligamento): Servidor
    {
        $servidor = new Servidor;
        $servidor->setRawAttributes(array_filter([
            'data_admissao' => $admissao,
            'data_desligamento' => $desligamento,
        ]));

        return $servidor;
    }
}
