<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\ExportacaoFolha;
use App\Enums\LancamentoStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class GeradorTxtFolhaService
{
    private const TAMANHO_CODIGO_EVENTO = 10;
    private const TAMANHO_MATRICULA = 13;
    private const TAMANHO_VALOR = 14;
    private const TAMANHO_LINHA = 37;

    public function gerar(?string $competencia = null): array
    {
        $query = LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)
            ->with(['evento', 'servidor']);

        if ($competencia) {
            $query->where('competencia', $competencia);
        }

        $lancamentos = $query->get();

        if ($lancamentos->isEmpty()) {
            throw new Exception('Nenhum lançamento conferido para exportação.' .
                ($competencia ? " (competência: {$competencia})" : ''));
        }

        $conteudo = '';
        $idsExportados = collect();

        foreach ($lancamentos as $lancamento) {
            $this->validarDadosObrigatorios($lancamento);

            $linha = $this->formatarLinha($lancamento);
            $this->validarTamanhoLinha($linha, $lancamento->id);

            $conteudo .= $linha . PHP_EOL;
            $idsExportados->push($lancamento->id);
        }

        $periodo = $competencia ? str_replace('-', '', $competencia) : now()->format('Ym');
        $nomeArquivo = $this->gerarNomeArquivo($competencia);
        $hashArquivo = hash('sha256', $conteudo);
        
        $this->salvarArquivo($nomeArquivo, $conteudo);

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
                "Lançamento #{$lancamento->id}: código do evento excede tamanho máximo (" . 
                self::TAMANHO_CODIGO_EVENTO . " caracteres)."
            );
        }

        if (strlen($lancamento->servidor->matricula) > self::TAMANHO_MATRICULA) {
            throw new Exception(
                "Lançamento #{$lancamento->id}: matrícula excede tamanho máximo (" . 
                self::TAMANHO_MATRICULA . " caracteres)."
            );
        }
    }

    private function formatarLinha(LancamentoSetorial $lancamento): string
    {
        $codigoEvento = $this->formatarCodigoEvento($lancamento->evento->codigo_evento);
        $matricula = $this->formatarMatricula($lancamento->servidor->matricula);
        $valor = $this->formatarValor($lancamento->valor ?? 0.00);

        return $codigoEvento . $matricula . $valor;
    }

    private function formatarCodigoEvento(string $codigo): string
    {
        return str_pad($codigo, self::TAMANHO_CODIGO_EVENTO, '0', STR_PAD_LEFT);
    }

    private function formatarMatricula(string $matricula): string
    {
        return str_pad($matricula, self::TAMANHO_MATRICULA, '0', STR_PAD_LEFT);
    }

    private function formatarValor(float $valor): string
    {
        $valorFormatado = number_format($valor, 2, '', '');
        return str_pad($valorFormatado, self::TAMANHO_VALOR, '0', STR_PAD_LEFT);
    }

    private function validarTamanhoLinha(string $linha, int $lancamentoId): void
    {
        if (strlen($linha) !== self::TAMANHO_LINHA) {
            throw new Exception(
                "Erro ao gerar linha do lançamento #{$lancamentoId}: " .
                "comprimento inválido (" . strlen($linha) . " caracteres, esperado " . 
                self::TAMANHO_LINHA . ")."
            );
        }
    }

    private function gerarNomeArquivo(?string $competencia = null): string
    {
        $prefixo = $competencia ? 'LOTE_' . str_replace('-', '', $competencia) : 'LOTE';
        return $prefixo . '_' . now()->format('Ymd_His') . '.txt';
    }

    private function salvarArquivo(string $nomeArquivo, string $conteudo): void
    {
        $caminho = storage_path("app/{$nomeArquivo}");
        file_put_contents($caminho, $conteudo);
    }
}
