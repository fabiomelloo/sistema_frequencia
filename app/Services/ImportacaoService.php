<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Models\Competencia;
use App\Enums\LancamentoStatus;
use App\Services\RegrasLancamentoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ImportacaoService
{
    private RegrasLancamentoService $regrasService;

    public function __construct(RegrasLancamentoService $regrasService)
    {
        $this->regrasService = $regrasService;
    }

    /**
     * Importa lançamentos a partir de um arquivo CSV.
     * 
     * Formato esperado:
     * matricula;codigo_evento;competencia;dias_trabalhados;valor;observacao
     */
    public function importarCsv(UploadedFile $arquivo, int $setorId): array
    {
        $conteudo = file_get_contents($arquivo->getRealPath());
        $linhas = array_filter(explode("\n", $conteudo));
        
        // Remover header se existir
        $primeiraLinha = trim($linhas[0] ?? '');
        if (stripos($primeiraLinha, 'matricula') !== false || stripos($primeiraLinha, 'matrícula') !== false) {
            array_shift($linhas);
        }

        $importados = 0;
        $erros = [];
        $linha = 1;

        foreach ($linhas as $l) {
            $linha++;
            $l = trim($l);
            if (empty($l)) continue;

            $campos = str_getcsv($l, ';');
            
            if (count($campos) < 4) {
                $erros[] = "Linha {$linha}: número insuficiente de campos (mínimo: 4).";
                continue;
            }

            $matricula = trim($campos[0] ?? '');
            $codigoEvento = trim($campos[1] ?? '');
            $competencia = trim($campos[2] ?? '');
            $diasTrabalhados = (int) trim($campos[3] ?? '0');
            $valor = (float) str_replace(',', '.', trim($campos[4] ?? '0'));
            $observacao = $this->sanitizarTexto(trim($campos[5] ?? ''));

            $servidor = Servidor::where('matricula', $matricula)
                ->where('setor_id', $setorId)
                ->first();

            if (!$servidor) {
                $erros[] = "Linha {$linha}: servidor matrícula '{$matricula}' não encontrado no setor.";
                continue;
            }

            $evento = EventoFolha::where('codigo_evento', $codigoEvento)
                ->where('ativo', true)
                ->first();

            if (!$evento) {
                $erros[] = "Linha {$linha}: evento código '{$codigoEvento}' não encontrado ou inativo.";
                continue;
            }

            if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
                $erros[] = "Linha {$linha}: competência '{$competencia}' inválida (esperado YYYY-MM).";
                continue;
            }

            if (LancamentoSetorial::existeDuplicata($servidor->id, $evento->id, $competencia)) {
                $erros[] = "Linha {$linha}: já existe lançamento para {$servidor->nome} / {$evento->descricao} na competência {$competencia}.";
                continue;
            }

            // Validar regras de negócio (competência aberta, servidor ativo, limites, etc.)
            try {
                $dadosValidacao = [
                    'competencia' => $competencia,
                    'dias_trabalhados' => $diasTrabalhados ?: null,
                    'valor' => $valor ?: null,
                    'observacao' => $observacao ?: null,
                ];
                $this->regrasService->validar($servidor, $evento, $dadosValidacao);
            } catch (InvalidArgumentException $e) {
                $erros[] = "Linha {$linha}: {$e->getMessage()}";
                continue;
            }

            try {
                $lancamento = LancamentoSetorial::create([
                    'servidor_id' => $servidor->id,
                    'evento_id' => $evento->id,
                    'setor_origem_id' => $setorId,
                    'competencia' => $competencia,
                    'dias_trabalhados' => $diasTrabalhados ?: null,
                    'valor' => $valor ?: null,
                    'observacao' => $observacao ?: "Importado via CSV",
                ]);

                $lancamento->status = LancamentoStatus::PENDENTE;
                $lancamento->save();

                $importados++;
            } catch (\Exception $e) {
                $erros[] = "Linha {$linha}: erro ao salvar — {$e->getMessage()}";
            }
        }

        return [
            'importados' => $importados,
            'erros' => $erros,
            'total_linhas' => count($linhas),
        ];
    }

    /**
     * Sanitiza texto para prevenir XSS
     */
    private function sanitizarTexto(string $texto): string
    {
        return htmlspecialchars(strip_tags($texto), ENT_QUOTES, 'UTF-8');
    }
}

