<?php

namespace App\Services;

use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Competencia;
use App\Enums\TipoEvento;
use InvalidArgumentException;
use Carbon\Carbon;

class RegrasLancamentoService
{
    public function validar(
        Servidor $servidor,
        EventoFolha $evento,
        array $dados,
        ?int $lancamentoId = null
    ): void {
        $competencia = $dados['competencia'] ?? now()->format('Y-m');

        // 1. Competência aberta
        $this->validarCompetenciaAberta($competencia);

        // 2. Servidor ativo na competência
        $this->validarServidorAtivo($servidor, $competencia);

        // 3. Dias individuais
        $this->validarDias($evento, $dados, $competencia);

        // 4. Limite de dias acumulados por servidor/competência
        $this->validarLimiteDias($servidor, $competencia, $dados, $lancamentoId);

        // 5. Periculosidade individual
        $this->validarPericulosidade($dados);

        // 6. Insalubridade individual
        $this->validarInsalubridade($dados);

        // 7. Incompatibilidade cruzada (insalubridade vs periculosidade entre lançamentos)
        $this->validarIncompatibilidadeCruzada($servidor, $competencia, $dados, $lancamentoId);

        // 8. Gratificação
        $this->validarGratificacao($evento, $dados);

        // 9. Adicional turno
        $this->validarAdicionalTurno($servidor, $evento, $dados);

        // 10. Adicional noturno
        $this->validarAdicionalNoturno($servidor, $evento, $dados);

        // 11. Valor mínimo/máximo do evento
        $this->validarValorLimites($evento, $dados);

        // 12. Limite de valor total acumulado por servidor na competência
        $this->validarValorTotalServidor($servidor, $competencia, $dados, $lancamentoId);
    }

    private function validarCompetenciaAberta(string $competencia): void
    {
        $comp = Competencia::buscarPorReferencia($competencia);
        
        // Se não existe competência cadastrada, permitir (modo legado)
        if (!$comp) {
            return;
        }

        if ($comp->estaFechada()) {
            throw new InvalidArgumentException(
                "A competência {$competencia} está fechada. Não é possível criar ou editar lançamentos."
            );
        }

        if ($comp->prazoExpirado()) {
            throw new InvalidArgumentException(
                "O prazo para lançamentos na competência {$competencia} expirou em {$comp->data_limite->format('d/m/Y')}."
            );
        }
    }

    private function validarServidorAtivo(Servidor $servidor, string $competencia): void
    {
        if (!$servidor->estaAtivoNaCompetencia($competencia)) {
            $msg = "O servidor {$servidor->nome} não estava ativo na competência {$competencia}.";
            
            if ($servidor->data_desligamento) {
                $msg .= " Desligamento em {$servidor->data_desligamento->format('d/m/Y')}.";
            }
            
            throw new InvalidArgumentException($msg);
        }
    }

    private function validarDias(EventoFolha $evento, array $dados, string $competencia): void
    {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if ($evento->exige_dias && empty($diasTrabalhados)) {
            throw new InvalidArgumentException('Dias trabalhados é obrigatório para este evento.');
        }

        if (!empty($diasTrabalhados)) {
            $diasNoMes = Carbon::createFromFormat('Y-m', $competencia)->daysInMonth;
            
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

    private function validarLimiteDias(Servidor $servidor, string $competencia, array $dados, ?int $lancamentoId): void
    {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? 0;
        if ($diasTrabalhados <= 0) {
            return;
        }

        $diasJaLancados = LancamentoSetorial::somaDiasServidor($servidor->id, $competencia, $lancamentoId);
        $diasNoMes = Carbon::createFromFormat('Y-m', $competencia)->daysInMonth;
        $total = $diasJaLancados + $diasTrabalhados;

        if ($total > $diasNoMes) {
            throw new InvalidArgumentException(
                "Limite de dias excedido para {$servidor->nome} na competência {$competencia}. " .
                "Já lançados: {$diasJaLancados} dias. Informado: {$diasTrabalhados} dias. " .
                "Total ({$total}) ultrapassa os {$diasNoMes} dias do mês."
            );
        }
    }

    private function validarIncompatibilidadeCruzada(Servidor $servidor, string $competencia, array $dados, ?int $lancamentoId): void
    {
        $mensagem = LancamentoSetorial::temIncompatibilidadeCruzada(
            $servidor->id,
            $competencia,
            $dados['porcentagem_insalubridade'] ?? null,
            $dados['porcentagem_periculosidade'] ?? null,
            $lancamentoId
        );

        if ($mensagem) {
            throw new InvalidArgumentException($mensagem);
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

    private function validarValorLimites(EventoFolha $evento, array $dados): void
    {
        $valor = $dados['valor'] ?? null;
        $valorGratificacao = $dados['valor_gratificacao'] ?? null;
        $valorTotal = $valor ?? $valorGratificacao;

        if (empty($valorTotal)) {
            return;
        }

        if ($evento->valor_minimo && $valorTotal < $evento->valor_minimo) {
            throw new InvalidArgumentException(
                "O valor R\$ " . number_format($valorTotal, 2, ',', '.') . 
                " está abaixo do mínimo permitido de R\$ " . number_format($evento->valor_minimo, 2, ',', '.') . 
                " para este evento."
            );
        }

        if ($evento->valor_maximo && $valorTotal > $evento->valor_maximo) {
            throw new InvalidArgumentException(
                "O valor R\$ " . number_format($valorTotal, 2, ',', '.') . 
                " está acima do máximo permitido de R\$ " . number_format($evento->valor_maximo, 2, ',', '.') . 
                " para este evento."
            );
        }
    }

    /**
     * Regra 12: Limite de valor total acumulado por servidor na competência.
     */
    private function validarValorTotalServidor(Servidor $servidor, string $competencia, array $dados, ?int $lancamentoId): void
    {
        $limiteTotal = \App\Models\Configuracao::get('limite_valor_total_servidor');
        if (empty($limiteTotal)) {
            return; // Sem limite configurado, pula validação
        }

        $limiteTotal = (float) $limiteTotal;
        $valorAtual = (float) ($dados['valor'] ?? $dados['valor_gratificacao'] ?? 0);

        if ($valorAtual <= 0) {
            return;
        }

        $query = LancamentoSetorial::where('servidor_id', $servidor->id)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [\App\Enums\LancamentoStatus::REJEITADO->value, \App\Enums\LancamentoStatus::ESTORNADO->value]);

        if ($lancamentoId) {
            $query->where('id', '!=', $lancamentoId);
        }

        $valorAcumulado = (float) $query->sum('valor');

        if (($valorAcumulado + $valorAtual) > $limiteTotal) {
            throw new InvalidArgumentException(
                "O valor total acumulado do servidor {$servidor->nome} nesta competência seria de R\$ " .
                number_format($valorAcumulado + $valorAtual, 2, ',', '.') .
                ", ultrapassando o limite de R\$ " . number_format($limiteTotal, 2, ',', '.') . "."
            );
        }
    }
}
