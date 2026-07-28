<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Enums\TipoEvento;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\User;
use App\Support\SystemDefaults;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegrasLancamentoService
{
    public function validar(
        Servidor $servidor,
        EventoFolha $evento,
        array $dados,
        ?int $lancamentoId = null,
        ?int $setorId = null,
        ?User $usuario = null
    ): void {
        $competencia = $dados['competencia'] ?? now()->format('Y-m');

        // 0a. Evento deve estar ativo
        $this->validarEventoAtivo($evento);

        // 0b. Evento autorizado para o setor
        if ($setorId) {
            $this->validarAutorizacaoEvento($evento, $setorId);
        }

        // 1. Competência aberta
        $competenciaModel = $this->validarCompetenciaAberta($competencia);

        // 2. Servidor ativo na competência
        $this->validarServidorAtivo($servidor, $competencia);

        // 3. Dias individuais (com proporcionalidade)
        $diasUteisAtivos = $competenciaModel->diasUteisAtivosDoServidor($servidor);
        $this->validarDias($evento, $dados, $competenciaModel, $diasUteisAtivos);

        // 4. Limite de dias acumulados por servidor/competência
        $this->validarLimiteDias($servidor, $competencia, $dados, $lancamentoId, $diasUteisAtivos);

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
        $this->validarRetroatividade($competencia, $dados, $lancamentoId, $usuario);

        // 14. Conflito turno/noturno no mesmo lançamento
        $this->validarConflitoTurnoNoturno($dados);

        // 15. Conflito turno/noturno entre lançamentos do mesmo servidor na competência
        $this->validarConflitoTurnoNoturnoCruzado($servidor, $competencia, $dados, $lancamentoId);

        // 16. Duplicata: mesmo servidor + evento + competência
        $this->validarDuplicata($servidor, $evento, $competencia, $lancamentoId);
    }

    /**
     * Evento deve estar ativo para receber lançamentos.
     */
    private function validarEventoAtivo(EventoFolha $evento): void
    {
        if (! $evento->ativo) {
            throw new InvalidArgumentException(
                "O evento \"{$evento->descricao}\" (código {$evento->codigo_evento}) está inativo. ".
                'Não é possível criar lançamentos para eventos desativados.'
            );
        }
    }

    /**
     * Evento deve ser autorizado para o setor que está lançando.
     */
    private function validarAutorizacaoEvento(EventoFolha $evento, int $setorId): void
    {
        if (! $evento->temDireitoNoSetor($setorId)) {
            throw new InvalidArgumentException(
                "O evento \"{$evento->descricao}\" não está autorizado para este setor. ".
                'Solicite a liberação ao administrador.'
            );
        }
    }

    private function validarCompetenciaAberta(string $competencia): Competencia
    {
        $comp = Competencia::buscarPorReferencia($competencia);

        // Todo lançamento deve pertencer a uma competência cadastrada.
        if (! $comp) {
            throw new InvalidArgumentException(
                "A competência {$competencia} não está cadastrada no sistema."
            );
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

        return $comp;
    }

    private function validarServidorAtivo(Servidor $servidor, string $competencia): void
    {
        if (! $servidor->estaAtivoNaCompetencia($competencia)) {
            $msg = "O servidor {$servidor->nome} não estava ativo na competência {$competencia}.";

            if ($servidor->data_desligamento) {
                $msg .= " Desligamento em {$servidor->data_desligamento->format('d/m/Y')}.";
            }

            throw new InvalidArgumentException($msg);
        }
    }

    private function validarDias(
        EventoFolha $evento,
        array $dados,
        Competencia $competencia,
        int $diasUteisAtivos
    ): void {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? null;

        if ($evento->exige_dias && empty($diasTrabalhados)) {
            throw new InvalidArgumentException('Dias trabalhados é obrigatório para este evento.');
        }

        if (! empty($diasTrabalhados)) {
            if ($diasTrabalhados < 1) {
                throw new InvalidArgumentException('Dias trabalhados deve ser pelo menos 1.');
            }

            if ($diasTrabalhados > $diasUteisAtivos) {
                $inicio = $competencia->inicioPeriodo()->format('d/m/Y');
                $fim = $competencia->fimPeriodo()->format('d/m/Y');
                throw new InvalidArgumentException(
                    "Dias trabalhados ({$diasTrabalhados}) excede o máximo permitido ".
                    "para o servidor no período da competência de {$inicio} a {$fim} ({$diasUteisAtivos} dias úteis)."
                );
            }

            if ($evento->dias_maximo && $diasTrabalhados > $evento->dias_maximo) {
                throw new InvalidArgumentException(
                    "Dias trabalhados não pode ser maior que {$evento->dias_maximo} para este evento."
                );
            }
        }
    }

    private function validarLimiteDias(
        Servidor $servidor,
        string $competencia,
        array $dados,
        ?int $lancamentoId,
        int $diasUteisAtivos
    ): void {
        $diasTrabalhados = $dados['dias_trabalhados'] ?? 0;
        if ($diasTrabalhados <= 0) {
            return;
        }

        $diasJaLancados = LancamentoSetorial::somaDiasServidor($servidor->id, $competencia, $lancamentoId);
        $diasLancados = (int) $dados['dias_trabalhados'];

        if ($diasLancados > $diasUteisAtivos) {
            throw new InvalidArgumentException(
                "O número de dias trabalhados ({$diasLancados}) não pode exceder ".
                "os dias úteis em que o servidor esteve ativo no período ({$diasUteisAtivos} dias)."
            );
        }

        $total = $diasJaLancados + $diasTrabalhados;

        if ($total > $diasUteisAtivos) {
            throw new InvalidArgumentException(
                "Limite de dias excedido para {$servidor->nome} na competência {$competencia}. ".
                "Já lançados: {$diasJaLancados} dias. Informado: {$diasTrabalhados} dias. ".
                "Total ({$total}) ultrapassa os {$diasUteisAtivos} dias úteis ".
                'em que o servidor esteve ativo no período.'
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

        if (! empty($porcentagemPericulosidade) && ! empty($porcentagemInsalubridade)) {
            throw new InvalidArgumentException(
                'Periculosidade e insalubridade não podem coexistir.'
            );
        }

        if (! empty($porcentagemPericulosidade) && empty($diasTrabalhados)) {
            throw new InvalidArgumentException(
                'Periculosidade exige dias trabalhados.'
            );
        }

        if (! empty($porcentagemPericulosidade) && $porcentagemPericulosidade !== 30) {
            throw new InvalidArgumentException(
                'Porcentagem de periculosidade deve ser 30%.'
            );
        }
    }

    private function validarInsalubridade(array $dados): void
    {
        $porcentagemInsalubridade = $dados['porcentagem_insalubridade'] ?? null;
        $porcentagemPericulosidade = $dados['porcentagem_periculosidade'] ?? null;

        if (! empty($porcentagemInsalubridade) && ! empty($porcentagemPericulosidade)) {
            throw new InvalidArgumentException(
                'Insalubridade e periculosidade não podem coexistir.'
            );
        }

        if (! empty($porcentagemInsalubridade)) {
            $valoresPermitidos = [10, 20, 40];
            if (! in_array($porcentagemInsalubridade, $valoresPermitidos)) {
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

        if (! empty($valorGratificacao) && ! empty($porcentagem)) {
            throw new InvalidArgumentException(
                'Gratificação não pode ter valor e porcentagem simultaneamente.'
            );
        }

        // Validação de intervalo da porcentagem
        if (! empty($porcentagem)) {
            $porcentagem = (float) $porcentagem;
            if ($porcentagem <= 0 || $porcentagem > 100) {
                throw new InvalidArgumentException(
                    "Porcentagem de gratificação deve estar entre 0,01% e 100%. Informado: {$porcentagem}%."
                );
            }

            // Validar também contra os limites min/max do evento, se configurados via porcentagem
            if ($evento->valor_minimo && $porcentagem < $evento->valor_minimo) {
                throw new InvalidArgumentException(
                    "Porcentagem de gratificação ({$porcentagem}%) está abaixo do mínimo configurado para este evento ({$evento->valor_minimo}%)."
                );
            }
            if ($evento->valor_maximo && $porcentagem > $evento->valor_maximo) {
                throw new InvalidArgumentException(
                    "Porcentagem de gratificação ({$porcentagem}%) está acima do máximo configurado para este evento ({$evento->valor_maximo}%)."
                );
            }
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

        if (! empty($adicionalTurno)) {
            if (! $servidor->funcao_vigia) {
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

        if (! empty($adicionalNoturno)) {
            if (! $servidor->trabalha_noturno) {
                throw new InvalidArgumentException(
                    'Adicional noturno permitido apenas para servidor que trabalha à noite.'
                );
            }

            if (empty($diasTrabalhados)) {
                throw new InvalidArgumentException(
                    'Adicional noturno exige dias trabalhados.'
                );
            }

            if (! empty($diasNoturnos) && ! empty($diasTrabalhados)) {
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
        if (! isset($dados['adicional_noturno']) || empty($dados['adicional_noturno'])) {
            return;
        }

        $tetoAdicionalNoturno = Configuracao::get('teto_adicional_noturno')
            ? (float) Configuracao::get('teto_adicional_noturno')
            : SystemDefaults::TETO_ADICIONAL_NOTURNO;

        $valor = (float) $dados['adicional_noturno'];

        if ($valor > $tetoAdicionalNoturno) {
            throw new InvalidArgumentException(
                'O valor do Adicional Noturno (R$ '.number_format($valor, 2, ',', '.').') '.
                'excede o teto permitido de R$ '.number_format($tetoAdicionalNoturno, 2, ',', '.').'.'
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
                'O valor R$ '.number_format($valorTotal, 2, ',', '.').
                ' está abaixo do mínimo permitido de R$ '.number_format($evento->valor_minimo, 2, ',', '.').
                ' para este evento.'
            );
        }

        if ($evento->valor_maximo && $valorTotal > $evento->valor_maximo) {
            throw new InvalidArgumentException(
                'O valor R$ '.number_format($valorTotal, 2, ',', '.').
                ' está acima do máximo permitido de R$ '.number_format($evento->valor_maximo, 2, ',', '.').
                ' para este evento.'
            );
        }
    }

    /**
     * Regra 12: Limite de valor total acumulado por servidor na competência.
     */
    private function validarValorTotalServidor(Servidor $servidor, string $competencia, array $dados, ?int $lancamentoId): void
    {
        $limiteTotal = Configuracao::get('limite_valor_total_servidor');
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
            ->whereNotIn('status', [
                LancamentoStatus::REJEITADO->value,
                LancamentoStatus::ESTORNADO->value,
                LancamentoStatus::CANCELADO->value,
            ]);

        if ($lancamentoId) {
            $query->where('id', '!=', $lancamentoId);
        }

        $valorAcumulado = (float) $query->sum('valor');

        if (($valorAcumulado + $valorAtual) > $limiteTotal) {
            throw new InvalidArgumentException(
                "O valor total acumulado do servidor {$servidor->nome} nesta competência seria de R\$ ".
                number_format($valorAcumulado + $valorAtual, 2, ',', '.').
                ', ultrapassando o limite de R$ '.number_format($limiteTotal, 2, ',', '.').'.'
            );
        }
    }

    /**
     * Regra #14: Limite de retroatividade e Controle Orçamentário.
     * Lançamentos comuns podem ser feitos para até X meses retroativos.
     * Lançamentos retroativos consomem um orçamento limite configurado.
     *
     * @param  ?User  $usuario  Usuário que está realizando o lançamento.
     *                          Se null, cai no auth()->user() como fallback (compatibilidade).
     */
    private function validarRetroatividade(string $competencia, array $dados = [], ?int $lancamentoId = null, ?User $usuario = null): void
    {
        $hoje = now()->format('Y-m');
        if ($competencia >= $hoje) {
            return; // Não é retroativo
        }

        $usuario = $usuario ?? auth()->user();
        $isAdmin = $usuario && $usuario->role === UserRole::ADMIN;

        $limiteRetroativo = (int) (Configuracao::get('meses_retroativos') ?? SystemDefaults::MESES_RETROATIVOS);
        $competenciaDate = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $limiteDate = now()->subMonths($limiteRetroativo)->startOfMonth();

        // 1. Barreira Temporal
        if ($competenciaDate->lt($limiteDate)) {
            // Apenas admins podem lançar além do limite retroativo
            if (! $isAdmin) {
                throw new InvalidArgumentException(
                    "A competência {$competencia} é anterior ao limite retroativo de {$limiteRetroativo} meses. ".
                    'Apenas administradores podem realizar lançamentos tão antigos.'
                );
            }
        }

        // 2. Barreira Financeira/Orçamentária (Apenas para retroativos)
        $limiteOrcamento = Configuracao::get('limite_orcamento_retroativo');
        if ($limiteOrcamento) {
            $limiteOrcamento = (float) $limiteOrcamento;
            $valorLancamento = (float) ($dados['valor'] ?? $dados['valor_gratificacao'] ?? 0);

            if ($valorLancamento > 0) {
                // Soma todos os lançamentos retroativos feitos no mês atual
                $mesAtual = now()->format('Y-m');

                $query = LancamentoSetorial::where('competencia', '<', $mesAtual)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->whereNotIn('status', [
                        LancamentoStatus::REJEITADO->value,
                        LancamentoStatus::ESTORNADO->value,
                        LancamentoStatus::CANCELADO->value,
                    ]);

                if ($lancamentoId) {
                    $query->where('id', '!=', $lancamentoId);
                }

                $totalConsumido = (float) $query->sum(DB::raw('COALESCE(valor, 0) + COALESCE(valor_gratificacao, 0)'));

                if (($totalConsumido + $valorLancamento) > $limiteOrcamento) {
                    throw new InvalidArgumentException(
                        'O valor deste lançamento (R$ '.number_format($valorLancamento, 2, ',', '.').') '.
                        'ultrapassa o orçamento disponível para pagamentos retroativos neste mês. '.
                        'Orçamento total: R$ '.number_format($limiteOrcamento, 2, ',', '.').'. '.
                        'Já consumido: R$ '.number_format($totalConsumido, 2, ',', '.').'.'
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

        if (! empty($adicionalTurno) && ! empty($adicionalNoturno)) {
            throw new InvalidArgumentException(
                'Adicional de turno e adicional noturno não podem coexistir no mesmo lançamento. '.
                'Crie lançamentos separados para cada adicional.'
            );
        }
    }

    /**
     * Regra #16: Conflito turno/noturno entre lançamentos distintos do mesmo servidor na competência.
     * Um servidor não pode ter adicional_turno em um lançamento e adicional_noturno em outro
     * dentro da mesma competência.
     */
    private function validarConflitoTurnoNoturnoCruzado(
        Servidor $servidor,
        string $competencia,
        array $dados,
        ?int $lancamentoId
    ): void {
        $temTurnoNovo = ! empty($dados['adicional_turno']);
        $temNoturnoNovo = ! empty($dados['adicional_noturno']);

        if (! $temTurnoNovo && ! $temNoturnoNovo) {
            return; // Lançamento sem adicional, nenhum conflito possível
        }

        $query = LancamentoSetorial::where('servidor_id', $servidor->id)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [
                LancamentoStatus::REJEITADO->value,
                LancamentoStatus::ESTORNADO->value,
                LancamentoStatus::CANCELADO->value,
            ]);

        if ($lancamentoId) {
            $query->where('id', '!=', $lancamentoId);
        }

        $existentes = $query->get(['adicional_turno', 'adicional_noturno']);

        foreach ($existentes as $existente) {
            if ($temTurnoNovo && ! empty($existente->adicional_noturno)) {
                throw new InvalidArgumentException(
                    'Servidor já possui lançamento com adicional noturno nesta competência. '.
                    'Adicional de turno e adicional noturno não podem coexistir na mesma competência.'
                );
            }
            if ($temNoturnoNovo && ! empty($existente->adicional_turno)) {
                throw new InvalidArgumentException(
                    'Servidor já possui lançamento com adicional de turno nesta competência. '.
                    'Adicional noturno e adicional de turno não podem coexistir na mesma competência.'
                );
            }
        }
    }

    /**
     * Regra #17: Impede duplicata de servidor + evento + competência.
     * Evita dois lançamentos idênticos no mesmo mês para o mesmo servidor.
     */
    private function validarDuplicata(
        Servidor $servidor,
        EventoFolha $evento,
        string $competencia,
        ?int $lancamentoId
    ): void {
        if (LancamentoSetorial::existeDuplicata($servidor->id, $evento->id, $competencia, $lancamentoId)) {
            throw new InvalidArgumentException(
                "Já existe um lançamento ativo para {$servidor->nome} com o evento \"".
                "{$evento->descricao}\" na competência {$competencia}. ".
                'Edite o lançamento existente em vez de criar um novo.'
            );
        }
    }
}
