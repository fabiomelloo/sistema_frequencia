<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\ExportacaoFolha;
use App\Models\LancamentoSetorial;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeradorTxtFolhaService
{
    private const TAMANHO_CODIGO_EVENTO = 10;

    private const TAMANHO_MATRICULA = 14;

    private const TAMANHO_VALOR = 15;

    private const TAMANHO_LINHA = 39; // 10 + 14 + 15

    public function gerar(?string $competencia = null): array
    {
        // Validação de negócio: competência deve existir. A exportação pode ocorrer após o fechamento.
        if ($competencia) {
            if (! Competencia::buscarPorReferencia($competencia)) {
                throw new Exception("Competencia {$competencia} nao cadastrada no sistema.");
            }
        }

        $query = LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)
            ->with(['evento', 'servidor'])
            ->lockForUpdate();

        if ($competencia) {
            $query->where('competencia', $competencia);
        }

        $lancamentos = $query->get();

        if ($lancamentos->isEmpty()) {
            throw new Exception('Nenhum lançamento conferido para exportação.'.
                ($competencia ? " (competência: {$competencia})" : ''));
        }

        // Validação de negócio: servidores inativos bloqueiam exportação
        $servidoresInativos = $lancamentos->filter(fn ($l) => ! $l->servidor->ativo);
        if ($servidoresInativos->isNotEmpty()) {
            $nomes = $servidoresInativos->pluck('servidor.nome')->unique()->implode(', ');
            throw new Exception(
                "Existem lançamentos com servidores inativos: {$nomes}. Rejeite-os antes de exportar."
            );
        }

        $conteudo = '';
        $idsExportados = collect();

        foreach ($lancamentos as $lancamento) {
            $this->validarDadosObrigatorios($lancamento);

            $linha = $this->formatarLinha($lancamento);
            $this->validarTamanhoLinha($linha, $lancamento->id);

            $conteudo .= $linha.PHP_EOL;
            $idsExportados->push($lancamento->id);
        }

        $periodo = $competencia ? str_replace('-', '', $competencia) : now()->format('Ym');
        $nomeArquivo = $this->gerarNomeArquivo($competencia);
        $hashArquivo = hash('sha256', $conteudo);

        $caminhoArquivo = $this->salvarArquivo($nomeArquivo, $conteudo);

        $exportacao = ExportacaoFolha::create([
            'periodo' => $periodo,
            'nome_arquivo' => $nomeArquivo,
            'hash_arquivo' => $hashArquivo,
            'usuario_id' => auth()->id(),
            'quantidade_lancamentos' => $lancamentos->count(),
            'data_exportacao' => now(),
        ]);

        $exportacao->lancamentos()->attach($idsExportados->toArray());

        Log::info('Exportação de folha realizada', [
            'exportacao_id' => $exportacao->id,
            'arquivo' => $nomeArquivo,
            'quantidade' => $lancamentos->count(),
            'usuario_id' => auth()->id(),
            'hash' => $hashArquivo,
            'competencia' => $competencia,
        ]);

        return [
            'nomeArquivo' => $nomeArquivo,
            'caminhoArquivo' => $caminhoArquivo,
            'idsExportados' => $idsExportados,
            'exportacaoId' => $exportacao->id,
            'quantidade' => $lancamentos->count(),
        ];
    }

    private function validarDadosObrigatorios(LancamentoSetorial $lancamento): void
    {
        if (empty($lancamento->evento->codigo_evento)) {
            throw new Exception("Lançamento #{$lancamento->id}: código do evento não informado.");
        }

        if (empty($lancamento->servidor->matricula)) {
            throw new Exception("Lançamento #{$lancamento->id}: matrícula do servidor não informada.");
        }

        if (strlen($lancamento->evento->codigo_evento) > self::TAMANHO_CODIGO_EVENTO) {
            throw new Exception(
                "Lançamento #{$lancamento->id}: código do evento excede tamanho máximo (".
                self::TAMANHO_CODIGO_EVENTO.' caracteres).'
            );
        }

        if (strlen($lancamento->servidor->matricula) > self::TAMANHO_MATRICULA) {
            throw new Exception(
                "Lançamento #{$lancamento->id}: matrícula excede tamanho máximo (".
                self::TAMANHO_MATRICULA.' caracteres).'
            );
        }
    }

    private function formatarLinha(LancamentoSetorial $lancamento): string
    {
        $codigoEvento = str_pad($lancamento->evento->codigo_evento, self::TAMANHO_CODIGO_EVENTO, '0', STR_PAD_LEFT);
        $matricula = str_pad($lancamento->servidor->matricula, self::TAMANHO_MATRICULA, '0', STR_PAD_LEFT);

        $valorCentavos = (int) round(($lancamento->valor ?? 0) * 100);
        $valor = str_pad($valorCentavos, self::TAMANHO_VALOR, '0', STR_PAD_LEFT);

        return $codigoEvento.$matricula.$valor;
    }

    private function validarTamanhoLinha(string $linha, int $lancamentoId): void
    {
        if (strlen($linha) !== self::TAMANHO_LINHA) {
            throw new Exception(
                "Erro ao gerar linha do lançamento #{$lancamentoId}: ".
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
