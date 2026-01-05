<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use Exception;

class GeradorTxtFolhaService
{
    /**
     * Gera arquivo TXT com layout de largura fixa
     * Formato: EVENTO (10 pos) + MATRÍCULA (13 pos) + VALOR (14 pos) = 37 caracteres
     * 
     * @return array Retorna ['nomeArquivo' => string, 'idsExportados' => Collection]
     */
    public function gerar(): array
    {
        $lancamentos = LancamentoSetorial::where('status', 'CONFERIDO')
            ->whereNotNull('valor') // Apenas lançamentos com valor
            ->where('valor', '>', 0) // Valor maior que zero
            ->with(['evento', 'servidor'])
            ->get();

        if ($lancamentos->isEmpty()) {
            throw new Exception('Nenhum lançamento conferido com valor para exportação.');
        }

        $conteudo = '';
        $idsExportados = collect();

        foreach ($lancamentos as $l) {
            // Validar dados obrigatórios
            if (empty($l->evento->codigo_evento) || empty($l->servidor->matricula) || is_null($l->valor)) {
                throw new Exception("Lançamento #{$l->id} possui dados incompletos para exportação.");
            }

            // Formatar código do evento (10 posições, zero à esquerda)
            $codigoEvento = str_pad($l->evento->codigo_evento, 10, '0', STR_PAD_LEFT);

            // Formatar matrícula (13 posições, zero à esquerda)
            $matricula = str_pad($l->servidor->matricula, 13, '0', STR_PAD_LEFT);

            // Formatar valor (14 posições, sem ponto/vírgula, 2 casas implícitas)
            $valorFormatado = number_format($l->valor, 2, '', '');
            $valor = str_pad($valorFormatado, 14, '0', STR_PAD_LEFT);

            // Montar linha com exatamente 37 caracteres
            $linha = $codigoEvento . $matricula . $valor;

            // Validação
            if (strlen($linha) !== 37) {
                throw new Exception("Erro ao gerar linha: comprimento inválido ({$l->id})");
            }

            $conteudo .= $linha . PHP_EOL;
            $idsExportados->push($l->id);
        }

        // Gerar nome do arquivo: LOTE_YYYYmm.txt
        $nomeArquivo = 'LOTE_' . now()->format('Ym') . '.txt';

        // Salvar no storage
        $caminho = storage_path("app/{$nomeArquivo}");
        file_put_contents($caminho, $conteudo);

        return [
            'nomeArquivo' => $nomeArquivo,
            'idsExportados' => $idsExportados,
        ];
    }
}
