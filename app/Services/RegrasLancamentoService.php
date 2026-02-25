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
        ?int $lancamentoId = null,
        ?int $setorId = null
    ): void {
        $competencia = $dados['competencia'] ?? now()->format('Y-m');

        // 0a. Evento deve estar ativo
        $this->validarEventoAtivo($evento);

        // 0b. Evento autorizado para o setor
        if ($setorId) {
            $this->validarAutorizacaoEvento($evento, $setorId);
        }

        // 1. Competência aberta
        $this->validarCompetenciaAberta($competencia);

        // 2. Servidor ativo na competência
        $this->validarServidorAtivo($servidor, $competencia);

        // 3. Dias individuais (com proporcionalidade)
        $this->validarDias($evento, $dados, $competencia, $servidor);

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

        // 10. Adicional noturno e Dias noturnos
        $this->validarAdicionalNoturno($servidor, $evento, $dados);
        $this->validarTetoAdicionalNoturno($dados);


        // 11. Valor mínimo/máximo do evento
        $this->validarValorLimites($evento, $dados);

        // 12. Limite de valor total acumulado por servidor na competência
        $this->validarValorTotalServidor($servidor, $competencia, $dados, $lancamentoId);

        // 13. Retroatividade máxima
        $this->validarRetroatividade($competencia, $dados, $lancamentoId);

        // 14. Conflito turno/noturno
        $this->validarConflitoTurnoNoturno($dados);
    }

    /**
     * Evento deve estar ativo para receber lançamentos.
     */
    private function validarEventoAtivo(EventoFolha $evento): void
    {
        if (!$evento->ativo) {
            throw new InvalidArgumentException(
                "O evento \"{$evento->descricao}\" (código {$evento->codigo_evento}) está inativo. " .
                "Não é possível criar lançamentos para eventos desativados."
            );
        }
    }

    /**
     * Evento deve ser autorizado para o setor que está lançando.
     */
    private function validarAutorizacaoEvento(EventoFolha $evento, int $setorId): void
    {
        if (!$evento->temDireitoNoSetor($setorId)) {
            throw new InvalidArgumentException(
                "O evento \"{$evento->descricao}\" não está autorizado para este setor. " .
                "Solicite a liberação ao administrador."
            );
        }
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

    private function validarDias(EventoFolha $evento, array $dados, string $competencia, ?Servidor $servidor = null): void
    {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if ($evento->exige_dias && empty($diasTrabalhados)) {
            throw new InvalidArgumentException('Dias trabalhados é obrigatório para este evento.');
        }

        if (!empty($diasTrabalhados)) {
            $diasNoMes = Carbon::createFromFormat('Y-m', $competencia)->daysInMonth;
            $diasMaximosPermitidos = $diasNoMes;

            // Dias proporcionais por admissão no meio do mês
            if ($servidor && $servidor->data_admissao) {
                $inicioMes = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
                $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();
                if ($servidor->data_admissao->gt($inicioMes) && $servidor->data_admissao->lte($fimMes)) {
                    $diasMaximosPermitidos = $fimMes->diffInDays($servidor->data_admissao) + 1;
                }
            }

            // Dias proporcionais por desligamento no meio do mês
            if ($servidor && $servidor->data_desligamento) {
                $inicioMes = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
                $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();
                if ($servidor->data_desligamento->gte($inicioMes) && $servidor->data_desligamento->lt($fimMes)) {
                    $diasAteDesligamento = $servidor->data_desligamento->diffInDays($inicioMes) + 1;
                    $diasMaximosPermitidos = min($diasMaximosPermitidos, $diasAteDesligamento);
                }
            }

            if ($diasTrabalhados < 1) {
                throw new InvalidArgumentException('Dias trabalhados deve ser pelo menos 1.');
            }

            if ($diasTrabalhados > $diasMaximosPermitidos) {
                $msgExtra = $diasMaximosPermitidos < $diasNoMes
                    ? " (proporcional — servidor ativo apenas {$diasMaximosPermitidos} dias neste mês)"
                    : '';
                throw new InvalidArgumentException(
                    "Dias trabalhados ({$diasTrabalhados}) excede o máximo permitido ({$diasMaximosPermitidos}){$msgExtra}."
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
        $diasUteisBase = \App\Models\Competencia::obterDiasUteis($competencia);

        // Subtrai dias de feriados e recessos locais parametrizados, se houver lógica adicional
        // Aqui já assumimos que obterDiasUteis() poderia descontar os feriados se implementado lá, senão usamos limite padrão.
        $diasLancados = (int) $dados['dias_trabalhados'];

        if ($diasLancados > $diasUteisBase) {
            throw new InvalidArgumentException(
                "O número de dias trabalhados ({$diasLancados}) não pode exceder os dias úteis do mês ({$diasUteisBase} dias)."
            );
        }

        $total = $diasJaLancados + $diasTrabalhados;

        if ($total > $diasUteisBase) { // Alterado para usar diasUteisBase
            throw new InvalidArgumentException(
                "Limite de dias excedido para {$servidor->nome} na competência {$competencia}. " .
                "Já lançados: {$diasJaLancados} dias. Informado: {$diasTrabalhados} dias. " .
                "Total ({$total}) ultrapassa os {$diasUteisBase} dias úteis do mês."
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

    private function validarTetoAdicionalNoturno(array $dados): void
    {
        if (!isset($dados['adicional_noturno']) || empty($dados['adicional_noturno'])) {
            return;
        }

        $tetoAdicionalNoturno = \App\Models\Configuracao::get('teto_adicional_noturno') ? (float) \App\Models\Configuracao::get('teto_adicional_noturno') : 500.00;

        $valor = (float) $dados['adicional_noturno'];

        if ($valor > $tetoAdicionalNoturno) {
            throw new InvalidArgumentException(
                "O valor do Adicional Noturno (R$ " . number_format($valor, 2, ',', '.') . ") " .
                "excede o teto permitido de R$ " . number_format($tetoAdicionalNoturno, 2, ',', '.') . "."
            );
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

    /**
     * Regra #14: Limite de retroatividade e Controle Orçamentário.
     * Lançamentos comuns podem ser feitos para até X meses retroativos.
     * Lançamentos retroativos consomem um orçamento limite configurado.
     */
    private function validarRetroatividade(string $competencia, array $dados = [], ?int $lancamentoId = null): void
    {
        $hoje = now()->format('Y-m');
        if ($competencia >= $hoje) {
            return; // Não é retroativo
        }

        $usuario = auth()->user();
        $isAdmin = $usuario && clone $usuario->isAdmin(); // Assuming isAdmin() exists or check role

        $limiteRetroativo = (int) (\App\Models\Configuracao::get('meses_retroativos') ?? 3);
        $competenciaDate = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $limiteDate = now()->subMonths($limiteRetroativo)->startOfMonth();

        // 1. Barreira Temporal
        if ($competenciaDate->lt($limiteDate)) {
            // Apenas admins podem lançar além do limite retroativo
            if (!$isAdmin) {
                throw new InvalidArgumentException(
                    "A competência {$competencia} é anterior ao limite retroativo de {$limiteRetroativo} meses. " .
                    "Apenas administradores podem realizar lançamentos tão antigos."
                );
            }
        }

        // 2. Barreira Financeira/Orçamentária (Apenas para retroativos)
        $limiteOrcamento = \App\Models\Configuracao::get('limite_orcamento_retroativo');
        if ($limiteOrcamento) {
            $limiteOrcamento = (float) $limiteOrcamento;
            $valorLancamento = (float) ($dados['valor'] ?? $dados['valor_gratificacao'] ?? 0);
            
            if ($valorLancamento > 0) {
                // Soma todos os lançamentos retroativos feitos no mês atual
                $mesAtual = now()->format('Y-m');
                
                $query = \App\Models\LancamentoSetorial::where('competencia', '<', $mesAtual)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->whereNotIn('status', [\App\Enums\LancamentoStatus::REJEITADO->value, \App\Enums\LancamentoStatus::ESTORNADO->value]);
                
                if ($lancamentoId) {
                    $query->where('id', '!=', $lancamentoId);
                }
                
                $totalConsumido = (float) $query->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(valor, 0) + COALESCE(valor_gratificacao, 0)'));
                
                if (($totalConsumido + $valorLancamento) > $limiteOrcamento) {
                    throw new InvalidArgumentException(
                        "O valor deste lançamento (R$ " . number_format($valorLancamento, 2, ',', '.') . ") " .
                        "ultrapassa o orçamento disponível para pagamentos retroativos neste mês. " .
                        "Orçamento total: R$ " . number_format($limiteOrcamento, 2, ',', '.') . ". " .
                        "Já consumido: R$ " . number_format($totalConsumido, 2, ',', '.') . "."
                    );
                }
            }
        }
    }

    /**
     * Regra #15: Conflito entre adicional de turno e adicional noturno no mesmo lançamento.
     */
    private function validarConflitoTurnoNoturno(array $dados): void
    {
        $adicionalTurno = $dados['adicional_turno'] ?? null;
        $adicionalNoturno = $dados['adicional_noturno'] ?? null;

        if (!empty($adicionalTurno) && !empty($adicionalNoturno)) {
            throw new InvalidArgumentException(
                'Adicional de turno e adicional noturno não podem coexistir no mesmo lançamento. ' .
                'Crie lançamentos separados para cada adicional.'
            );
        }
    }

}
