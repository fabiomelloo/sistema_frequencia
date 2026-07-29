<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Enums\ProjecaoExportacaoStatus;
use App\Models\Competencia;
use App\Models\ExportacaoFolha;
use App\Models\LancamentoSetorial;
use App\Models\ProjecaoExportacaoFolha;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeradorTxtFolhaService
{
    private const TAMANHO_CODIGO_EVENTO = 10;

    private const TAMANHO_MATRICULA = 14;

    private const TAMANHO_VALOR = 15;

    private const TAMANHO_LINHA = 39; // 10 + 14 + 15

    public function gerar(?string $competencia = null): array
    {
        if ($competencia && ! Competencia::buscarPorReferencia($competencia)) {
            throw new Exception("Competencia {$competencia} nao cadastrada no sistema.");
        }

        $query = LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)
            ->with(['evento', 'servidor'])
            ->orderBy('id')
            ->lockForUpdate();

        if ($competencia) {
            $query->where('competencia', $competencia);
        }

        $lancamentos = $query->get();
        $projecoes = ProjecaoExportacaoFolha::query()
            ->where('status', ProjecaoExportacaoStatus::PRONTA)
            ->whereNull('exportacao_id')
            ->when(
                $competencia,
                fn ($query) => $query->whereHas(
                    'competencia',
                    fn ($competencias) => $competencias->where('referencia', $competencia)
                )
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lancamentos->isEmpty() && $projecoes->isEmpty()) {
            throw new Exception('Nenhum lançamento conferido para exportação.'.
                ($competencia ? " (competência: {$competencia})" : ''));
        }

        $chavesCandidatas = $lancamentos->map(fn (LancamentoSetorial $lancamento): array => [
            'chave' => "{$lancamento->servidor_id}|{$lancamento->evento->codigo_evento}",
            'descricao' => "{$lancamento->servidor->matricula}/{$lancamento->evento->codigo_evento}",
        ])->concat($projecoes->map(fn (ProjecaoExportacaoFolha $projecao): array => [
            'chave' => "{$projecao->servidor_id}|{$projecao->codigo_evento}",
            'descricao' => "{$projecao->matricula}/{$projecao->codigo_evento}",
        ]));
        $conflitos = $chavesCandidatas->groupBy('chave')->filter(fn ($grupo) => $grupo->count() > 1);
        if ($conflitos->isNotEmpty()) {
            $itens = $conflitos->map(fn ($grupo): string => $grupo->first()['descricao'])->implode(', ');

            throw new Exception(
                "Existem itens duplicados entre a frequência nativa e os lançamentos legados: {$itens}. ".
                'Mantenha uma única origem antes de exportar.'
            );
        }

        $servidoresInativos = $lancamentos->filter(fn ($lancamento) => ! $lancamento->servidor->ativo);
        if ($servidoresInativos->isNotEmpty()) {
            $nomes = $servidoresInativos->pluck('servidor.nome')->unique()->implode(', ');
            throw new Exception(
                "Existem lançamentos com servidores inativos: {$nomes}. Rejeite-os antes de exportar."
            );
        }

        $conteudo = '';
        $idsExportados = collect();
        $idsProjecoesExportadas = collect();

        foreach ($lancamentos as $lancamento) {
            $origem = "Lançamento #{$lancamento->id}";
            $codigoEvento = $lancamento->evento->codigo_evento;
            $matricula = $lancamento->servidor->matricula;
            $this->validarDadosObrigatorios($codigoEvento, $matricula, $origem);

            $linha = $this->formatarLinha($codigoEvento, $matricula, $lancamento->valor);
            $this->validarTamanhoLinha($linha, $origem);

            $conteudo .= $linha.PHP_EOL;
            $idsExportados->push($lancamento->id);
        }

        foreach ($projecoes as $projecao) {
            $origem = "Item aprovado #{$projecao->folha_frequencia_item_id}";
            $this->validarDadosObrigatorios($projecao->codigo_evento, $projecao->matricula, $origem);

            $linha = $this->formatarLinha($projecao->codigo_evento, $projecao->matricula, $projecao->valor);
            $this->validarTamanhoLinha($linha, $origem);

            $conteudo .= $linha.PHP_EOL;
            $idsProjecoesExportadas->push($projecao->id);
        }

        $periodo = $competencia ? str_replace('-', '', $competencia) : now()->format('Ym');
        $nomeArquivo = $this->gerarNomeArquivo($competencia);
        $hashArquivo = hash('sha256', $conteudo);
        $caminhoArquivo = $this->salvarArquivo($nomeArquivo, $conteudo);
        $quantidade = $lancamentos->count() + $projecoes->count();

        try {
            $exportacao = ExportacaoFolha::create([
                'periodo' => $periodo,
                'nome_arquivo' => $nomeArquivo,
                'hash_arquivo' => $hashArquivo,
                'usuario_id' => auth()->id(),
                'quantidade_lancamentos' => $quantidade,
                'data_exportacao' => now(),
            ]);

            $exportacao->lancamentos()->attach($idsExportados->toArray());
            LancamentoSetorial::query()->whereKey($idsExportados->toArray())->update([
                'status' => LancamentoStatus::EXPORTADO->value,
                'exportado_em' => now(),
            ]);
            ProjecaoExportacaoFolha::query()->whereKey($idsProjecoesExportadas->toArray())->update([
                'status' => ProjecaoExportacaoStatus::EXPORTADA->value,
                'exportacao_id' => $exportacao->id,
                'exportado_em' => now(),
            ]);

            Log::info('Exportação de folha realizada', [
                'exportacao_id' => $exportacao->id,
                'arquivo' => $nomeArquivo,
                'quantidade' => $quantidade,
                'quantidade_legada' => $lancamentos->count(),
                'quantidade_nativa' => $projecoes->count(),
                'usuario_id' => auth()->id(),
                'hash' => $hashArquivo,
                'competencia' => $competencia,
            ]);

            return [
                'nomeArquivo' => $nomeArquivo,
                'caminhoArquivo' => $caminhoArquivo,
                'idsExportados' => $idsExportados,
                'idsProjecoesExportadas' => $idsProjecoesExportadas,
                'exportacaoId' => $exportacao->id,
                'quantidade' => $quantidade,
                'quantidadeLegada' => $lancamentos->count(),
                'quantidadeNativa' => $projecoes->count(),
            ];
        } catch (Throwable $e) {
            Storage::disk('local')->delete($caminhoArquivo);
            throw $e;
        }
    }

    private function validarDadosObrigatorios(?string $codigoEvento, ?string $matricula, string $origem): void
    {
        if (empty($codigoEvento)) {
            throw new Exception("{$origem}: código do evento não informado.");
        }

        if (empty($matricula)) {
            throw new Exception("{$origem}: matrícula do servidor não informada.");
        }

        if (strlen($codigoEvento) > self::TAMANHO_CODIGO_EVENTO) {
            throw new Exception(
                "{$origem}: código do evento excede tamanho máximo (".
                self::TAMANHO_CODIGO_EVENTO.' caracteres).'
            );
        }

        if (strlen($matricula) > self::TAMANHO_MATRICULA) {
            throw new Exception(
                "{$origem}: matrícula excede tamanho máximo (".
                self::TAMANHO_MATRICULA.' caracteres).'
            );
        }
    }

    private function formatarLinha(string $codigoEvento, string $matricula, mixed $valorInformado): string
    {
        $codigoEvento = str_pad($codigoEvento, self::TAMANHO_CODIGO_EVENTO, '0', STR_PAD_LEFT);
        $matricula = str_pad($matricula, self::TAMANHO_MATRICULA, '0', STR_PAD_LEFT);

        $valorCentavos = (int) round(($valorInformado ?? 0) * 100);
        $valor = str_pad($valorCentavos, self::TAMANHO_VALOR, '0', STR_PAD_LEFT);

        return $codigoEvento.$matricula.$valor;
    }

    private function validarTamanhoLinha(string $linha, string $origem): void
    {
        if (strlen($linha) !== self::TAMANHO_LINHA) {
            throw new Exception(
                "Erro ao gerar linha de {$origem}: ".
                'comprimento inválido ('.strlen($linha).' caracteres, esperado '.
                self::TAMANHO_LINHA.').'
            );
        }
    }

    private function gerarNomeArquivo(?string $competencia = null): string
    {
        $prefixo = $competencia ? 'LOTE_'.str_replace('-', '', $competencia) : 'LOTE';

        return $prefixo.'_'.now()->format('Ymd_His').'.txt';
    }

    private function salvarArquivo(string $nomeArquivo, string $conteudo): string
    {
        $caminho = "exportacoes/{$nomeArquivo}";

        if (! Storage::disk('local')->put($caminho, $conteudo)) {
            throw new Exception('Não foi possível salvar o arquivo de exportação no armazenamento privado.');
        }

        return $caminho;
    }
}
