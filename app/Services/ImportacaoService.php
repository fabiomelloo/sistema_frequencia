<?php

namespace App\Services;

use App\Enums\ImportacaoFinalidade;
use App\Enums\ImportacaoLinhaStatus;
use App\Enums\ImportacaoStatus;
use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\Importacao;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportacaoService
{
    public function __construct(private RegrasLancamentoService $regrasService) {}

    /**
     * Mantém o contrato anterior para chamadas internas e testes.
     *
     * @return array{importados: int, validos: int, invalidos: int, erros: array, total_linhas: int, linhas: array}
     */
    public function importarCsv(
        string|UploadedFile $caminhoArquivo,
        int $setorId,
        User $usuario,
        bool $estrito = false,
    ): array {
        $this->assertImportacaoLegadaHabilitada();

        return $this->processarCsv($caminhoArquivo, $setorId, $usuario, true, $estrito);
    }

    /**
     * Executa todas as validações sem criar lançamentos.
     *
     * @return array{importados: int, validos: int, invalidos: int, erros: array, total_linhas: int, linhas: array}
     */
    public function analisarCsv(string|UploadedFile $caminhoArquivo, int $setorId, User $usuario): array
    {
        $this->assertImportacaoLegadaHabilitada();

        return $this->processarCsv($caminhoArquivo, $setorId, $usuario, false, false);
    }

    public function criarLote(UploadedFile $arquivo, int $setorId, User $usuario): Importacao
    {
        $this->assertImportacaoLegadaHabilitada();

        $nomeArmazenado = Str::uuid()->toString().'.csv';
        $caminho = $arquivo->storeAs("importacoes/{$setorId}", $nomeArmazenado, 'local');

        if (! $caminho) {
            throw new Exception('Não foi possível armazenar o arquivo de importação.');
        }

        $caminhoAbsoluto = Storage::disk('local')->path($caminho);

        try {
            $analise = $this->analisarCsv($caminhoAbsoluto, $setorId, $usuario);

            return DB::transaction(function () use ($arquivo, $caminho, $caminhoAbsoluto, $setorId, $usuario, $analise): Importacao {
                $importacao = Importacao::create([
                    'setor_id' => $setorId,
                    'usuario_id' => $usuario->id,
                    'nome_original' => $arquivo->getClientOriginalName(),
                    'caminho_arquivo' => $caminho,
                    'hash_arquivo' => hash_file('sha256', $caminhoAbsoluto),
                    'tipo_arquivo' => 'CSV',
                    'finalidade' => ImportacaoFinalidade::LANCAMENTOS,
                    'status' => $analise['invalidos'] > 0
                        ? ImportacaoStatus::INVALIDA
                        : ImportacaoStatus::PENDENTE,
                    'total_linhas' => $analise['total_linhas'],
                    'linhas_validas' => $analise['validos'],
                    'linhas_invalidas' => $analise['invalidos'],
                ]);

                $this->salvarLinhas($importacao, $analise['linhas']);

                return $importacao;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($caminho);
            throw $e;
        }
    }

    /**
     * @return array{importados: int, validos: int, invalidos: int, erros: array, total_linhas: int, linhas: array}
     */
    public function confirmarLote(Importacao $importacao, User $usuario): array
    {
        $this->assertImportacaoLegadaHabilitada();

        if ($importacao->finalidade !== ImportacaoFinalidade::LANCAMENTOS) {
            throw new InvalidArgumentException('Este lote não pertence à importação de lançamentos.');
        }
        if ($importacao->setor_id !== $usuario->setor_id) {
            throw new InvalidArgumentException('O lote não pertence ao setor do usuário.');
        }

        if (! $importacao->podeProcessar()) {
            throw new InvalidArgumentException('O lote não está apto para processamento.');
        }

        $caminho = Storage::disk('local')->path($importacao->caminho_arquivo);
        if (! is_file($caminho) || ! hash_equals($importacao->hash_arquivo, hash_file('sha256', $caminho))) {
            throw new InvalidArgumentException('O arquivo original não está disponível ou falhou na verificação de integridade.');
        }

        $reanalise = $this->analisarCsv($caminho, $importacao->setor_id, $usuario);
        if ($reanalise['invalidos'] > 0) {
            DB::transaction(function () use ($importacao, $reanalise): void {
                $importacao->linhas()->delete();
                $this->salvarLinhas($importacao, $reanalise['linhas']);
                $importacao->update([
                    'status' => ImportacaoStatus::INVALIDA,
                    'linhas_validas' => $reanalise['validos'],
                    'linhas_invalidas' => $reanalise['invalidos'],
                ]);
            });

            throw new InvalidArgumentException('O estado do sistema mudou e o lote deixou de ser válido. Revise a prévia atualizada.');
        }

        return DB::transaction(function () use ($caminho, $importacao, $usuario): array {
            $resultado = $this->importarCsv($caminho, $importacao->setor_id, $usuario, true);

            foreach ($resultado['linhas'] as $linha) {
                $importacao->linhas()
                    ->where('numero_linha', $linha['numero_linha'])
                    ->update([
                        'status' => ImportacaoLinhaStatus::PROCESSADA->value,
                        'lancamento_id' => $linha['lancamento_id'],
                        'updated_at' => now(),
                    ]);
            }

            $importacao->update([
                'status' => ImportacaoStatus::PROCESSADA,
                'processada_em' => now(),
            ]);

            return $resultado;
        });
    }

    /**
     * @return array{importados: int, validos: int, invalidos: int, erros: array, total_linhas: int, linhas: array}
     */
    private function processarCsv(
        string|UploadedFile $caminhoArquivo,
        int $setorId,
        User $usuario,
        bool $persistir,
        bool $estrito,
    ): array {
        [$linhas, $temCabecalho] = $this->lerArquivo($caminhoArquivo);
        [$servidores, $eventos, $duplicatas] = $this->carregarContexto($linhas, $setorId);

        $erros = [];
        $linhasAnalisadas = [];
        $importados = 0;

        if ($persistir) {
            DB::beginTransaction();
        }

        try {
            foreach ($linhas as $indice => $conteudoLinha) {
                $numeroLinha = $indice + ($temCabecalho ? 2 : 1);
                $resultadoLinha = $this->processarLinha(
                    $conteudoLinha,
                    $numeroLinha,
                    $setorId,
                    $usuario,
                    $servidores,
                    $eventos,
                    $duplicatas,
                    $persistir,
                );

                $linhasAnalisadas[] = $resultadoLinha;

                foreach ($resultadoLinha['erros'] as $erro) {
                    $erros[] = "Linha {$numeroLinha}: {$erro}";
                }

                if ($resultadoLinha['valida']) {
                    $importados += $persistir ? 1 : 0;
                }
            }

            if ($persistir && $estrito && $erros !== []) {
                throw new InvalidArgumentException('A confirmação foi cancelada porque o lote possui linhas inválidas.');
            }

            if ($persistir) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if ($persistir && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Erro no processamento de importação CSV', [
                'erro' => $e->getMessage(),
                'setor_id' => $setorId,
                'usuario_id' => $usuario->id,
            ]);

            throw $e;
        }

        $validos = count(array_filter($linhasAnalisadas, fn (array $linha): bool => $linha['valida']));

        return [
            'importados' => $importados,
            'validos' => $validos,
            'invalidos' => count($linhasAnalisadas) - $validos,
            'erros' => $erros,
            'total_linhas' => count($linhas),
            'linhas' => $linhasAnalisadas,
        ];
    }

    /**
     * @param  Collection<string, Servidor>  $servidores
     * @param  Collection<string, EventoFolha>  $eventos
     * @param  array<string, bool>  $duplicatas
     * @return array{numero_linha: int, conteudo_original: string, dados: ?array, erros: array, valida: bool, lancamento_id: ?int}
     */
    private function processarLinha(
        string $conteudoLinha,
        int $numeroLinha,
        int $setorId,
        User $usuario,
        Collection $servidores,
        Collection $eventos,
        array &$duplicatas,
        bool $persistir,
    ): array {
        $colunas = str_getcsv($conteudoLinha, ';', '"', '\\');
        $erros = [];
        $dados = null;
        $lancamentoId = null;

        if (count($colunas) < 4) {
            $erros[] = 'Número insuficiente de campos (mínimo: 4).';
        } else {
            $colunas = array_pad($colunas, 12, null);

            try {
                $dados = [
                    'matricula' => trim((string) $colunas[0]),
                    'codigo_evento' => trim((string) $colunas[1]),
                    'competencia' => trim((string) $colunas[2]),
                    'dias_trabalhados' => $this->parseNumero($colunas[3] ?? null, 'dias_trabalhados'),
                    'dias_noturnos' => $this->parseNumero($colunas[4] ?? null, 'dias_noturnos'),
                    'valor' => $this->parseValor($colunas[5] ?? null, 'valor'),
                    'valor_gratificacao' => $this->parseValor($colunas[6] ?? null, 'valor_gratificacao'),
                    'porcentagem_insalubridade' => $this->parseNumero($colunas[7] ?? null, 'porcentagem_insalubridade'),
                    'porcentagem_periculosidade' => $this->parseNumero($colunas[8] ?? null, 'porcentagem_periculosidade'),
                    'adicional_turno' => $this->parseValor($colunas[9] ?? null, 'adicional_turno'),
                    'adicional_noturno' => $this->parseValor($colunas[10] ?? null, 'adicional_noturno'),
                    'observacao' => $this->sanitizarTexto(trim((string) ($colunas[11] ?? ''))) ?: null,
                ];
            } catch (InvalidArgumentException $e) {
                $erros[] = $e->getMessage();
            }
        }

        if ($dados !== null && $erros === []) {
            $servidor = $servidores->get($dados['matricula']);
            $evento = $eventos->get($dados['codigo_evento']);

            if (! $servidor) {
                $erros[] = "Servidor com matrícula '{$dados['matricula']}' não encontrado no setor.";
            }

            if (! $evento) {
                $erros[] = "Evento '{$dados['codigo_evento']}' não encontrado ou inativo.";
            } elseif (! $evento->setoresComDireito->contains('id', $setorId)) {
                $erros[] = "O setor não possui direito ao evento '{$evento->descricao}'.";
            }

            if (! preg_match('/^\d{4}-\d{2}$/', $dados['competencia'])) {
                $erros[] = "Competência '{$dados['competencia']}' inválida (esperado YYYY-MM).";
            }

            if ($servidor && $evento && $erros === []) {
                $chaveDuplicidade = "{$servidor->id}-{$evento->id}-{$dados['competencia']}";

                if (isset($duplicatas[$chaveDuplicidade])) {
                    $erros[] = "Já existe lançamento para {$servidor->nome} / {$evento->descricao} nesta competência.";
                } else {
                    try {
                        $this->regrasService->validar($servidor, $evento, $dados, null, $setorId, $usuario);

                        if ($persistir) {
                            $setorOrigemId = $this->obterSetorNaCompetencia($servidor, $dados['competencia'])
                                ?? $servidor->setor_id;
                            $lancamento = LancamentoSetorial::create([
                                'servidor_id' => $servidor->id,
                                'evento_id' => $evento->id,
                                'setor_origem_id' => $setorOrigemId,
                                'criado_por_id' => $usuario->id,
                                'competencia' => $dados['competencia'],
                                'dias_trabalhados' => $dados['dias_trabalhados'],
                                'dias_noturnos' => $dados['dias_noturnos'],
                                'valor' => $dados['valor'],
                                'valor_gratificacao' => $dados['valor_gratificacao'],
                                'porcentagem_insalubridade' => $dados['porcentagem_insalubridade'],
                                'porcentagem_periculosidade' => $dados['porcentagem_periculosidade'],
                                'adicional_turno' => $dados['adicional_turno'],
                                'adicional_noturno' => $dados['adicional_noturno'],
                                'observacao' => $dados['observacao'] ?: 'Importado via CSV',
                            ]);
                            $lancamentoId = $lancamento->id;
                        }

                        $duplicatas[$chaveDuplicidade] = true;
                    } catch (InvalidArgumentException $e) {
                        $erros[] = $e->getMessage();
                    }
                }
            }
        }

        return [
            'numero_linha' => $numeroLinha,
            'conteudo_original' => $conteudoLinha,
            'dados' => $dados,
            'erros' => $erros,
            'valida' => $erros === [],
            'lancamento_id' => $lancamentoId,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: bool}
     */
    private function lerArquivo(string|UploadedFile $arquivo): array
    {
        $caminho = $arquivo instanceof UploadedFile ? $arquivo->getRealPath() : $arquivo;
        if (! $caminho || ! is_file($caminho)) {
            throw new Exception('Arquivo de importação não encontrado.');
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) {
            throw new Exception('Não foi possível ler o arquivo de importação.');
        }

        $primeiraLinha = trim($linhas[0] ?? '');
        $temCabecalho = stripos($primeiraLinha, 'matricula') !== false
            || stripos($primeiraLinha, 'matrícula') !== false;

        if ($temCabecalho) {
            array_shift($linhas);
        }

        if ($linhas === []) {
            throw new InvalidArgumentException('O arquivo não possui linhas de dados.');
        }

        if (count($linhas) > 500) {
            throw new InvalidArgumentException('O arquivo CSV excede o limite de 500 linhas.');
        }

        return [array_values($linhas), $temCabecalho];
    }

    /**
     * @param  array<int, string>  $linhas
     * @return array{0: Collection<string, Servidor>, 1: Collection<string, EventoFolha>, 2: array<string, bool>}
     */
    private function carregarContexto(array $linhas, int $setorId): array
    {
        $matriculas = [];
        $codigosEvento = [];
        $competencias = [];

        foreach ($linhas as $linha) {
            $colunas = str_getcsv($linha, ';', '"', '\\');
            if (count($colunas) < 3) {
                continue;
            }

            $matriculas[] = trim((string) $colunas[0]);
            $codigosEvento[] = trim((string) $colunas[1]);
            $competencias[] = trim((string) $colunas[2]);
        }

        $servidores = Servidor::whereIn('matricula', array_unique(array_filter($matriculas)))
            ->where('setor_id', $setorId)
            ->with(['lotacoes' => fn ($query) => $query->orderBy('data_inicio', 'desc')])
            ->get()
            ->keyBy('matricula');

        $eventos = EventoFolha::whereIn('codigo_evento', array_unique(array_filter($codigosEvento)))
            ->where('ativo', true)
            ->with(['setoresComDireito' => fn ($query) => $query->where('setor_id', $setorId)])
            ->get()
            ->keyBy('codigo_evento');

        $duplicatas = [];
        if ($servidores->isNotEmpty() && $eventos->isNotEmpty()) {
            $existentes = LancamentoSetorial::whereIn('servidor_id', $servidores->pluck('id'))
                ->whereIn('evento_id', $eventos->pluck('id'))
                ->whereIn('competencia', array_unique(array_filter($competencias)))
                ->whereNotIn('status', [
                    LancamentoStatus::REJEITADO->value,
                    LancamentoStatus::ESTORNADO->value,
                    LancamentoStatus::CANCELADO->value,
                ])
                ->get(['servidor_id', 'evento_id', 'competencia']);

            foreach ($existentes as $existente) {
                $duplicatas["{$existente->servidor_id}-{$existente->evento_id}-{$existente->competencia}"] = true;
            }
        }

        return [$servidores, $eventos, $duplicatas];
    }

    /**
     * @param  array<int, array>  $linhas
     */
    private function assertImportacaoLegadaHabilitada(): void
    {
        if (! config('operations.legacy.spreadsheet_import_enabled', false)) {
            throw new \LogicException('A importacao por planilha foi desativada. Use o lancamento nativo do sistema.');
        }
    }

    private function salvarLinhas(Importacao $importacao, array $linhas): void
    {
        foreach ($linhas as $linha) {
            $importacao->linhas()->create([
                'numero_linha' => $linha['numero_linha'],
                'conteudo_original' => $linha['conteudo_original'],
                'dados' => $linha['dados'],
                'status' => $linha['valida']
                    ? ImportacaoLinhaStatus::VALIDA
                    : ImportacaoLinhaStatus::INVALIDA,
                'erros' => $linha['erros'] ?: null,
                'lancamento_id' => $linha['lancamento_id'],
            ]);
        }
    }

    private function obterSetorNaCompetencia(Servidor $servidor, string $competencia): ?int
    {
        if ($servidor->lotacoes->isEmpty()) {
            return null;
        }

        [$inicioPeriodo, $fimPeriodo] = Competencia::periodoDaReferencia($competencia);

        $lotacao = $servidor->lotacoes->first(function ($lotacao) use ($inicioPeriodo, $fimPeriodo): bool {
            $inicio = $lotacao->data_inicio instanceof Carbon
                ? $lotacao->data_inicio
                : Carbon::parse($lotacao->data_inicio);
            $fim = $lotacao->data_fim
                ? ($lotacao->data_fim instanceof Carbon ? $lotacao->data_fim : Carbon::parse($lotacao->data_fim))
                : null;

            return $inicio->lte($fimPeriodo) && ($fim === null || $fim->gte($inicioPeriodo));
        });

        return $lotacao?->setor_id;
    }

    private function sanitizarTexto(string $texto): string
    {
        return trim(strip_tags($texto));
    }

    private function parseNumero(?string $valor, string $campo): ?int
    {
        $valor = trim($valor ?? '');
        if ($valor === '') {
            return null;
        }

        if (! preg_match('/^-?\d+$/', $valor)) {
            throw new InvalidArgumentException("O campo {$campo} deve ser um número inteiro.");
        }

        return (int) $valor;
    }

    private function parseValor(?string $valor, string $campo): ?float
    {
        $valor = str_replace(',', '.', trim($valor ?? ''));
        if ($valor === '') {
            return null;
        }

        if (! is_numeric($valor)) {
            throw new InvalidArgumentException("O campo {$campo} deve ser numérico.");
        }

        return (float) $valor;
    }
}
