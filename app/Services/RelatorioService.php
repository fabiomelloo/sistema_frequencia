<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Enums\LancamentoStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RelatorioService
{
    /**
     * Resumo de uma competência: totais por setor, status, valores.
     */
    public function resumoCompetencia(string $competencia): array
    {
        $lancamentos = LancamentoSetorial::where('competencia', $competencia)
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->get();

        // Contadores por status
        $porStatus = [];
        foreach (LancamentoStatus::cases() as $status) {
            $porStatus[$status->value] = $lancamentos->where('status', $status)->count();
        }

        // Por setor
        $porSetor = $lancamentos->groupBy('setor_origem_id')->map(function ($grupo) {
            return (object) [
                'setor_nome' => $grupo->first()->setorOrigem->nome ?? 'N/A',
                'qtd' => $grupo->count(),
                'pendentes' => $grupo->where('status', LancamentoStatus::PENDENTE)->count(),
                'conferidos' => $grupo->where('status', LancamentoStatus::CONFERIDO)->count()
                    + $grupo->where('status', LancamentoStatus::CONFERIDO_SETORIAL)->count(),
                'rejeitados' => $grupo->where('status', LancamentoStatus::REJEITADO)->count(),
                'exportados' => $grupo->where('status', LancamentoStatus::EXPORTADO)->count(),
                'valor_total' => $grupo->sum('valor') + $grupo->sum('valor_gratificacao')
                    + $grupo->sum('adicional_turno') + $grupo->sum('adicional_noturno'),
            ];
        })->values();

        // Por evento
        $porEvento = $lancamentos->groupBy('evento_id')->map(function ($grupo) {
            return (object) [
                'descricao' => $grupo->first()->evento->descricao ?? 'N/A',
                'codigo_evento' => $grupo->first()->evento->codigo_evento ?? '',
                'qtd' => $grupo->count(),
                'valor_total' => $grupo->sum('valor') + $grupo->sum('valor_gratificacao'),
            ];
        })->values();

        return [
            'competencia' => $competencia,
            'total_lancamentos' => $lancamentos->count(),
            'por_status' => $porStatus,
            'por_setor' => $porSetor,
            'por_evento' => $porEvento,
            'valor_geral' => $lancamentos->sum('valor') + $lancamentos->sum('valor_gratificacao')
                + $lancamentos->sum('adicional_turno') + $lancamentos->sum('adicional_noturno'),
        ];
    }

    /**
     * Comparativo entre competências.
     */
    public function comparativo(string $competenciaA, string $competenciaB): array
    {
        return [
            'a' => $this->resumoCompetencia($competenciaA),
            'b' => $this->resumoCompetencia($competenciaB),
        ];
    }

    /**
     * Folha-espelho: todos os eventos de um servidor em uma competência.
     */
    public function folhaEspelho(int $servidorId, string $competencia): array
    {
        $servidor = Servidor::with('setor')->findOrFail($servidorId);
        
        $lancamentos = LancamentoSetorial::where('servidor_id', $servidorId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [LancamentoStatus::REJEITADO->value, LancamentoStatus::ESTORNADO->value])
            ->with(['evento', 'setorOrigem', 'validador'])
            ->orderBy('created_at')
            ->get();

        return [
            'servidor' => $servidor,
            'competencia' => $competencia,
            'lancamentos' => $lancamentos,
            'total_dias' => $lancamentos->sum('dias_trabalhados'),
            'total_valor' => $lancamentos->sum('valor') + $lancamentos->sum('valor_gratificacao')
                + $lancamentos->sum('adicional_turno') + $lancamentos->sum('adicional_noturno'),
        ];
    }

    /**
     * Gera CSV string para exportação Excel.
     */
    public function gerarCsv(string $competencia): string
    {
        $lancamentos = LancamentoSetorial::where('competencia', $competencia)
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->orderBy('setor_origem_id')
            ->orderBy('servidor_id')
            ->get();

        $csv = "Competência;Setor;Matrícula;Servidor;Evento;Código;Dias;Valor;Status\n";

        foreach ($lancamentos as $l) {
            $valor = $l->valor ?? $l->valor_gratificacao ?? $l->adicional_turno ?? $l->adicional_noturno ?? 0;
            $csv .= implode(';', [
                $l->competencia,
                $l->setorOrigem->nome ?? '',
                $l->servidor->matricula ?? '',
                $l->servidor->nome ?? '',
                $l->evento->descricao ?? '',
                $l->evento->codigo_evento ?? '',
                $l->dias_trabalhados ?? '',
                number_format($valor, 2, ',', '.'),
                $l->status->value ?? $l->status,
            ]) . "\n";
        }

        return $csv;
    }

    /**
     * C3: Relatório de divergências pré-exportação.
     * Identifica outliers, servidores sem lançamento, duplicatas entre meses, valores fora do padrão.
     */
    public function divergencias(string $competencia): array
    {
        $lancamentos = LancamentoSetorial::where('competencia', $competencia)
            ->whereNotIn('status', [LancamentoStatus::REJEITADO->value, LancamentoStatus::ESTORNADO->value])
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->get();

        $alertas = [];

        // 1. Servidores ativos sem nenhum lançamento na competência
        $servidoresComLancamento = $lancamentos->pluck('servidor_id')->unique();
        $setoresAtivos = $lancamentos->pluck('setor_origem_id')->unique();
        $servidoresSemLancamento = Servidor::where('ativo', true)
            ->whereIn('setor_id', $setoresAtivos)
            ->whereNotIn('id', $servidoresComLancamento)
            ->get();

        foreach ($servidoresSemLancamento as $s) {
            $alertas[] = [
                'tipo' => 'SERVIDOR_SEM_LANCAMENTO',
                'severidade' => 'aviso',
                'mensagem' => "Servidor {$s->nome} (mat. {$s->matricula}) ativo mas sem lançamentos na competência.",
                'servidor_id' => $s->id,
            ];
        }

        // 2. Valores outlier (acima de 2x a média por evento)
        $porEvento = $lancamentos->groupBy('evento_id');
        foreach ($porEvento as $eventoId => $grupo) {
            $valores = $grupo->pluck('valor')->filter()->values();
            if ($valores->count() < 3) continue;
            
            $media = $valores->avg();
            $limite = $media * 2;
            
            foreach ($grupo as $l) {
                if ($l->valor && $l->valor > $limite) {
                    $alertas[] = [
                        'tipo' => 'VALOR_OUTLIER',
                        'severidade' => 'atencao',
                        'mensagem' => "Lançamento #{$l->id} ({$l->servidor->nome}) tem valor R$ " . number_format($l->valor, 2, ',', '.') .
                            " — acima de 2x a média (R$ " . number_format($media, 2, ',', '.') . ") para o evento {$l->evento->descricao}.",
                        'lancamento_id' => $l->id,
                    ];
                }
            }
        }

        // 3. Duplicidade entre meses (mesmo servidor+evento no mês anterior com valor diferente)
        $mesAnterior = Carbon::createFromFormat('Y-m', $competencia)->subMonth()->format('Y-m');
        $lancamentosAnterior = LancamentoSetorial::where('competencia', $mesAnterior)
            ->whereNotIn('status', [LancamentoStatus::REJEITADO->value, LancamentoStatus::ESTORNADO->value])
            ->get()
            ->keyBy(fn($l) => "{$l->servidor_id}_{$l->evento_id}");

        foreach ($lancamentos as $l) {
            $chave = "{$l->servidor_id}_{$l->evento_id}";
            if (isset($lancamentosAnterior[$chave])) {
                $anterior = $lancamentosAnterior[$chave];
                if ($l->valor != $anterior->valor || $l->dias_trabalhados != $anterior->dias_trabalhados) {
                    $alertas[] = [
                        'tipo' => 'VARIACAO_ENTRE_MESES',
                        'severidade' => 'info',
                        'mensagem' => "Lançamento #{$l->id} ({$l->servidor->nome}, {$l->evento->descricao}) tem valores diferentes do mês anterior.",
                        'lancamento_id' => $l->id,
                        'anterior_id' => $anterior->id,
                    ];
                }
            }
        }

        // 4. Setores sem exportação recente
        $setoresSemExportacao = Setor::whereNotIn('id', function ($query) use ($competencia) {
            $query->select('setor_origem_id')
                ->from('lancamentos_setoriais')
                ->where('competencia', $competencia)
                ->where('status', LancamentoStatus::EXPORTADO->value);
        })
            ->whereIn('id', $setoresAtivos)
            ->get();

        foreach ($setoresSemExportacao as $s) {
            $alertas[] = [
                'tipo' => 'SETOR_SEM_EXPORTACAO',
                'severidade' => 'aviso',
                'mensagem' => "Setor \"{$s->nome}\" tem lançamentos na competência mas nenhum exportado.",
                'setor_id' => $s->id,
            ];
        }

        return [
            'competencia' => $competencia,
            'total_alertas' => count($alertas),
            'alertas' => $alertas,
            'resumo' => [
                'servidores_sem_lancamento' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'SERVIDOR_SEM_LANCAMENTO')),
                'valores_outlier' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'VALOR_OUTLIER')),
                'variacoes_entre_meses' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'VARIACAO_ENTRE_MESES')),
                'setores_sem_exportacao' => count(array_filter($alertas, fn($a) => $a['tipo'] === 'SETOR_SEM_EXPORTACAO')),
            ],
        ];
    }
}
