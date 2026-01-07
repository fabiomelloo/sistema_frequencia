<?php

namespace App\Services;

use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Enums\TipoEvento;
use InvalidArgumentException;
use Carbon\Carbon;

class RegrasLancamentoService
{
    public function validar(
        Servidor $servidor,
        EventoFolha $evento,
        array $dados
    ): void {
        $this->validarDias($evento, $dados);
        $this->validarPericulosidade($dados);
        $this->validarGratificacao($evento, $dados);
        $this->validarAdicionalTurno($servidor, $evento, $dados);
        $this->validarAdicionalNoturno($servidor, $evento, $dados);
        $this->validarInsalubridade($dados);
    }

    private function validarDias(EventoFolha $evento, array $dados): void
    {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if ($evento->exige_dias && empty($diasTrabalhados)) {
            throw new InvalidArgumentException('Dias trabalhados é obrigatório para este evento.');
        }

        if (!empty($diasTrabalhados)) {
            $diasNoMes = Carbon::now()->daysInMonth;
            
            if ($diasTrabalhados < 1) {
                throw new InvalidArgumentException('Dias trabalhados deve ser pelo menos 1.');
            }

            if ($diasTrabalhados > $diasNoMes) {
                throw new InvalidArgumentException(
                    "Dias trabalhados não pode ser maior que os dias do mês ({$diasNoMes})."
                );
            }

            if ($evento->dias_maximo && $diasTrabalhados > $evento->dias_maximo) {
                throw new InvalidArgumentException(
                    "Dias trabalhados não pode ser maior que {$evento->dias_maximo} para este evento."
                );
            }
        }
    }

    private function validarPericulosidade(array $dados): void
    {
        $porcentagemPericulosidade = $dados['porcentagem_periculosidade'] ?? null;
        $porcentagemInsalubridade = $dados['porcentagem_insalubridade'] ?? null;
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if (!empty($porcentagemPericulosidade) && !empty($porcentagemInsalubridade)) {
            throw new InvalidArgumentException(
                'Periculosidade e insalubridade não podem coexistir.'
            );
        }

        if (!empty($porcentagemPericulosidade) && empty($diasTrabalhados)) {
            throw new InvalidArgumentException(
                'Periculosidade exige dias trabalhados.'
            );
        }

        if (!empty($porcentagemPericulosidade) && $porcentagemPericulosidade !== 30) {
            throw new InvalidArgumentException(
                'Porcentagem de periculosidade deve ser 30%.'
            );
        }
    }

    private function validarInsalubridade(array $dados): void
    {
        $porcentagemInsalubridade = $dados['porcentagem_insalubridade'] ?? null;
        $porcentagemPericulosidade = $dados['porcentagem_periculosidade'] ?? null;

        if (!empty($porcentagemInsalubridade) && !empty($porcentagemPericulosidade)) {
            throw new InvalidArgumentException(
                'Insalubridade e periculosidade não podem coexistir.'
            );
        }

        if (!empty($porcentagemInsalubridade)) {
            $valoresPermitidos = [10, 20, 40];
            if (!in_array($porcentagemInsalubridade, $valoresPermitidos)) {
                throw new InvalidArgumentException(
                    'Porcentagem de insalubridade deve ser 10%, 20% ou 40%.'
                );
            }
        }
    }

    private function validarGratificacao(EventoFolha $evento, array $dados): void
    {
        if ($evento->tipo_evento !== TipoEvento::GRATIFICACAO) {
            return;
        }

        $valorGratificacao = $dados['valor_gratificacao'] ?? null;
        $porcentagem = $dados['porcentagem'] ?? null;

        if (empty($valorGratificacao) && empty($porcentagem)) {
            throw new InvalidArgumentException(
                'Gratificação exige valor ou porcentagem.'
            );
        }

        if (!empty($valorGratificacao) && !empty($porcentagem)) {
            throw new InvalidArgumentException(
                'Gratificação não pode ter valor e porcentagem simultaneamente.'
            );
        }
    }

    private function validarAdicionalTurno(
        Servidor $servidor,
        EventoFolha $evento,
        array $dados
    ): void {
        if ($evento->tipo_evento !== TipoEvento::ADICIONAL_TURNO) {
            return;
        }

        $adicionalTurno = $dados['adicional_turno'] ?? null;
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if (!empty($adicionalTurno)) {
            if (!$servidor->funcao_vigia) {
                throw new InvalidArgumentException(
                    'Adicional de turno permitido apenas para servidor com função de vigia.'
                );
            }

            if (empty($diasTrabalhados)) {
                throw new InvalidArgumentException(
                    'Adicional de turno exige dias trabalhados.'
                );
            }
        }
    }

    private function validarAdicionalNoturno(
        Servidor $servidor,
        EventoFolha $evento,
        array $dados
    ): void {
        if ($evento->tipo_evento !== TipoEvento::ADICIONAL_NOTURNO) {
            return;
        }

        $adicionalNoturno = $dados['adicional_noturno'] ?? null;
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;
        $diasNoturnos = $dados['dias_noturnos'] ?? null;

        if (!empty($adicionalNoturno)) {
            if (!$servidor->trabalha_noturno) {
                throw new InvalidArgumentException(
                    'Adicional noturno permitido apenas para servidor que trabalha à noite.'
                );
            }

            if (empty($diasTrabalhados)) {
                throw new InvalidArgumentException(
                    'Adicional noturno exige dias trabalhados.'
                );
            }

            if (!empty($diasNoturnos) && !empty($diasTrabalhados)) {
                if ($diasNoturnos > $diasTrabalhados) {
                    throw new InvalidArgumentException(
                        'Dias noturnos não podem ser maiores que dias trabalhados.'
                    );
                }
            }
        }
    }
}
