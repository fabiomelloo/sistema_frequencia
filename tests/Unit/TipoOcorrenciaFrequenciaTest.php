<?php

namespace Tests\Unit;

use App\Enums\TipoOcorrenciaFrequencia;
use PHPUnit\Framework\TestCase;

class TipoOcorrenciaFrequenciaTest extends TestCase
{
    public function test_define_matriz_simetrica_de_coexistencia(): void
    {
        $permitidas = [
            [TipoOcorrenciaFrequencia::VIAGEM, TipoOcorrenciaFrequencia::HORARIO_REDUZIDO],
            [TipoOcorrenciaFrequencia::ATIVIDADE_EXTERNA, TipoOcorrenciaFrequencia::PONTO_FACULTATIVO],
            [TipoOcorrenciaFrequencia::FALTA, TipoOcorrenciaFrequencia::INCONSISTENCIA_REGISTRO],
        ];

        foreach ($permitidas as [$primeiro, $segundo]) {
            $this->assertTrue($primeiro->podeCoexistirCom($segundo));
            $this->assertTrue($segundo->podeCoexistirCom($primeiro));
        }

        $proibidas = [
            [TipoOcorrenciaFrequencia::FALTA, TipoOcorrenciaFrequencia::FALTA],
            [TipoOcorrenciaFrequencia::FALTA, TipoOcorrenciaFrequencia::ATESTADO_MEDICO],
            [TipoOcorrenciaFrequencia::PONTO_FACULTATIVO, TipoOcorrenciaFrequencia::HORARIO_REDUZIDO],
            [TipoOcorrenciaFrequencia::VIAGEM, TipoOcorrenciaFrequencia::CURSO_CAPACITACAO],
        ];

        foreach ($proibidas as [$primeiro, $segundo]) {
            $this->assertFalse($primeiro->podeCoexistirCom($segundo));
            $this->assertFalse($segundo->podeCoexistirCom($primeiro));
        }
    }
}
