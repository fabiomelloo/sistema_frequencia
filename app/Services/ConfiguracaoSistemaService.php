<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Support\SystemDefaults;
use Illuminate\Support\Facades\DB;

class ConfiguracaoSistemaService
{
    private const DEFINICOES = [
        'sla_dias_conferencia' => [
            'descricao' => 'Prazo máximo, em dias, para a conferência central.',
            'tipo' => 'number',
            'default' => SystemDefaults::SLA_DIAS_CONFERENCIA,
            'regras' => ['required', 'integer', 'min:1', 'max:60'],
        ],
        'sla_dias_alerta' => [
            'descricao' => 'Antecedência, em dias, para alertar sobre o vencimento do SLA.',
            'tipo' => 'number',
            'default' => SystemDefaults::SLA_DIAS_ALERTA,
            'regras' => ['required', 'integer', 'min:0', 'max:60', 'lte:sla_dias_conferencia'],
        ],
        'limite_rejeicoes_lancamento' => [
            'descricao' => 'Quantidade máxima de rejeições antes do bloqueio do lançamento.',
            'tipo' => 'number',
            'default' => SystemDefaults::LIMITE_REJEICOES_LANCAMENTO,
            'regras' => ['required', 'integer', 'min:1', 'max:20'],
        ],
        'teto_adicional_noturno' => [
            'descricao' => 'Valor máximo permitido para adicional noturno.',
            'tipo' => 'number',
            'step' => '0.01',
            'default' => SystemDefaults::TETO_ADICIONAL_NOTURNO,
            'regras' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ],
        'meses_retroativos' => [
            'descricao' => 'Quantidade máxima de meses retroativos permitidos.',
            'tipo' => 'number',
            'default' => SystemDefaults::MESES_RETROATIVOS,
            'regras' => ['required', 'integer', 'min:0', 'max:24'],
        ],
        'limite_orcamento_retroativo' => [
            'descricao' => 'Limite orçamentário para lançamentos retroativos; vazio desativa o limite.',
            'tipo' => 'number',
            'step' => '0.01',
            'default' => null,
            'regras' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ],
        'limite_delegacoes_setor' => [
            'descricao' => 'Quantidade máxima de delegações ativas por setor.',
            'tipo' => 'number',
            'default' => SystemDefaults::LIMITE_DELEGACOES_SETOR,
            'regras' => ['required', 'integer', 'min:1', 'max:20'],
        ],
        'duracao_maxima_delegacao_dias' => [
            'descricao' => 'Duração máxima de uma delegação, em dias.',
            'tipo' => 'number',
            'default' => SystemDefaults::DURACAO_MAXIMA_DELEGACAO_DIAS,
            'regras' => ['required', 'integer', 'min:1', 'max:365'],
        ],
        'limite_valor_total_servidor' => [
            'descricao' => 'Teto mensal por servidor; vazio desativa o limite.',
            'tipo' => 'number',
            'step' => '0.01',
            'default' => null,
            'regras' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ],
        'transferir_lancamentos_ao_mudar_setor' => [
            'descricao' => 'Transfere lançamentos pendentes quando o servidor muda de setor.',
            'tipo' => 'boolean',
            'default' => 'false',
            'regras' => ['required', 'in:true,false'],
        ],
        'email_usuario_sistema' => [
            'descricao' => 'Conta técnica usada pelas rotinas automáticas.',
            'tipo' => 'email',
            'default' => 'admin@example.com',
            'regras' => ['required', 'email:rfc', 'max:255'],
        ],
    ];

    public function configuracoesParaEdicao(): array
    {
        $valores = Configuracao::query()
            ->whereIn('chave', array_keys(self::DEFINICOES))
            ->pluck('valor', 'chave');

        return collect(self::DEFINICOES)
            ->map(fn (array $definicao, string $chave): array => [
                'chave' => $chave,
                'descricao' => $definicao['descricao'],
                'tipo' => $definicao['tipo'],
                'step' => $definicao['step'] ?? null,
                'valor' => $valores->has($chave) ? $valores->get($chave) : $definicao['default'],
            ])
            ->values()
            ->all();
    }

    public function regras(): array
    {
        return collect(self::DEFINICOES)
            ->mapWithKeys(fn (array $definicao, string $chave): array => [$chave => $definicao['regras']])
            ->all();
    }

    public function chavesEditaveis(): array
    {
        return array_keys(self::DEFINICOES);
    }

    public function atualizar(array $dados): int
    {
        return DB::transaction(function () use ($dados): int {
            $atualizadas = 0;

            foreach ($this->chavesEditaveis() as $chave) {
                $valor = $this->normalizar($dados[$chave] ?? null);
                $configuracao = Configuracao::query()->lockForUpdate()->firstOrNew(['chave' => $chave]);

                if ($configuracao->exists && $configuracao->valor === $valor) {
                    continue;
                }

                $configuracao->valor = $valor;
                $configuracao->descricao ??= self::DEFINICOES[$chave]['descricao'];
                $configuracao->save();

                AuditService::registrar(
                    $configuracao->wasRecentlyCreated ? 'CRIOU' : 'EDITOU',
                    'Configuracao',
                    $configuracao->id,
                    "Configuração '{$chave}' atualizada."
                );
                $atualizadas++;
            }

            return $atualizadas;
        });
    }

    private function normalizar(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return trim((string) $valor);
    }
}
