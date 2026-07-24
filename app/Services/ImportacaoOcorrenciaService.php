<?php

namespace App\Services;

use App\Enums\ImportacaoFinalidade;
use App\Enums\ImportacaoLinhaStatus;
use App\Enums\ImportacaoStatus;
use App\Enums\TipoOcorrenciaFrequencia;
use App\Models\Competencia;
use App\Models\Importacao;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportacaoOcorrenciaService
{
    private const CABECALHO = [
        'matricula',
        'competencia',
        'tipo',
        'data_inicio',
        'data_fim',
        'dias_especificos',
        'justificada',
        'possui_comprovacao',
        'referencia_documento',
        'observacao_original',
    ];

    public function __construct(private OcorrenciaFrequenciaService $ocorrenciaService) {}

    public function criarLote(UploadedFile $arquivo, int $setorId, User $usuario): Importacao
    {
        $this->assertImportacaoLegadaHabilitada();

        $nomeArmazenado = Str::uuid()->toString().'.csv';
        $caminho = $arquivo->storeAs("importacoes/{$setorId}/ocorrencias", $nomeArmazenado, 'local');

        if (! $caminho) {
            throw new Exception('Não foi possível armazenar o arquivo de ocorrências.');
        }

        $caminhoAbsoluto = Storage::disk('local')->path($caminho);

        try {
            $analise = $this->analisarCsv($caminhoAbsoluto, $setorId);

            return DB::transaction(function () use ($arquivo, $caminho, $caminhoAbsoluto, $setorId, $usuario, $analise): Importacao {
                $importacao = Importacao::create([
                    'setor_id' => $setorId,
                    'usuario_id' => $usuario->id,
                    'nome_original' => $arquivo->getClientOriginalName(),
                    'caminho_arquivo' => $caminho,
                    'hash_arquivo' => hash_file('sha256', $caminhoAbsoluto),
                    'tipo_arquivo' => 'CSV',
                    'finalidade' => ImportacaoFinalidade::OCORRENCIAS,
                    'status' => $analise['invalidos'] > 0 ? ImportacaoStatus::INVALIDA : ImportacaoStatus::PENDENTE,
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
     * @return array{validos: int, invalidos: int, total_linhas: int, linhas: array}
     */
    public function analisarCsv(string $caminho, int $setorId): array
    {
        $this->assertImportacaoLegadaHabilitada();

        $linhas = $this->lerArquivo($caminho);
        [$servidores, $competencias, $assinaturas] = $this->carregarContexto($linhas, $setorId);
        $analisadas = [];

        foreach ($linhas as $indice => $conteudo) {
            $analisadas[] = $this->analisarLinha(
                $conteudo,
                $indice + 2,
                $setorId,
                $servidores,
                $competencias,
                $assinaturas,
            );
        }

        $validos = count(array_filter($analisadas, fn (array $linha): bool => $linha['valida']));

        return [
            'validos' => $validos,
            'invalidos' => count($analisadas) - $validos,
            'total_linhas' => count($analisadas),
            'linhas' => $analisadas,
        ];
    }

    /**
     * @return array{importados: int}
     */
    public function confirmarLote(Importacao $importacao, User $usuario): array
    {
        $this->assertImportacaoLegadaHabilitada();

        if ($importacao->finalidade !== ImportacaoFinalidade::OCORRENCIAS) {
            throw new InvalidArgumentException('Este lote não pertence à importação de ocorrências.');
        }

        if ($importacao->setor_id !== $usuario->setor_id || ! $importacao->podeProcessar()) {
            throw new InvalidArgumentException('O lote não está apto para processamento por este setor.');
        }

        $caminho = Storage::disk('local')->path($importacao->caminho_arquivo);
        if (! is_file($caminho) || ! hash_equals($importacao->hash_arquivo, hash_file('sha256', $caminho))) {
            throw new InvalidArgumentException('O arquivo original não está disponível ou falhou na verificação de integridade.');
        }

        $reanalise = $this->analisarCsv($caminho, $importacao->setor_id);
        if ($reanalise['invalidos'] > 0) {
            $this->atualizarAnaliseInvalida($importacao, $reanalise);
            throw new InvalidArgumentException('O estado do sistema mudou e o lote deixou de ser válido. Revise a prévia atualizada.');
        }

        return DB::transaction(function () use ($importacao, $usuario, $reanalise): array {
            $importados = 0;

            foreach ($reanalise['linhas'] as $linha) {
                $linhaImportada = $importacao->linhas()->where('numero_linha', $linha['numero_linha'])->firstOrFail();
                $ocorrencia = $this->ocorrenciaService->criar(
                    $linha['dados'],
                    $usuario,
                    'CSV',
                    $linhaImportada->id,
                );
                $linhaImportada->update([
                    'status' => ImportacaoLinhaStatus::PROCESSADA,
                    'ocorrencia_id' => $ocorrencia->id,
                ]);
                $importados++;
            }

            $importacao->update([
                'status' => ImportacaoStatus::PROCESSADA,
                'processada_em' => now(),
            ]);

            return ['importados' => $importados];
        });
    }

    public static function conteudoModelo(): string
    {
        $cabecalho = implode(';', self::CABECALHO);
        $exemplo = 'MAT001;2026-07;ATESTADO_MEDICO;;;2026-06-19,2026-06-25;SIM;SIM;Atestado entregue ao RH;Dias 19 e 25 - atestado médico';

        return "\xEF\xBB\xBF{$cabecalho}\r\n{$exemplo}\r\n";
    }

    /**
     * @param  Collection<string, Servidor>  $servidores
     * @param  Collection<string, Competencia>  $competencias
     * @param  array<string, bool>  $assinaturas
     * @return array{numero_linha: int, conteudo_original: string, dados: ?array, erros: array, valida: bool}
     */
    private function analisarLinha(
        string $conteudo,
        int $numeroLinha,
        int $setorId,
        Collection $servidores,
        Collection $competencias,
        array &$assinaturas,
    ): array {
        $colunas = array_pad(str_getcsv($conteudo, ';', '"', '\\'), count(self::CABECALHO), null);
        $erros = [];
        $dados = null;

        if (count(array_filter($colunas, fn ($valor) => $valor !== null)) > count(self::CABECALHO)) {
            $erros[] = 'A linha possui mais colunas que o modelo.';
        }

        try {
            $matricula = trim((string) $colunas[0]);
            $referencia = trim((string) $colunas[1]);
            $servidor = $servidores->get($matricula);
            $competencia = $competencias->get($referencia);
            $tipo = $this->parseTipo($colunas[2]);
            $dataInicio = $this->parseData($colunas[3], 'data_inicio');
            $dataFim = $this->parseData($colunas[4], 'data_fim');
            $dias = $this->parseDias($colunas[5]);

            if (! $servidor) {
                $erros[] = "Servidor com matrícula '{$matricula}' não encontrado no setor.";
            }
            if (! $competencia) {
                $erros[] = "Competência '{$referencia}' não encontrada.";
            } elseif (! $competencia->estaAberta()) {
                $erros[] = "A competência '{$referencia}' está fechada.";
            }
            if (! $dataInicio && $dias === []) {
                $erros[] = 'Informe um período ou pelo menos um dia específico.';
            }
            if (($dataInicio && ! $dataFim) || (! $dataInicio && $dataFim)) {
                $erros[] = 'O período exige data inicial e final.';
            } elseif ($dataInicio && $dataFim && $dataFim->lt($dataInicio)) {
                $erros[] = 'A data final deve ser igual ou posterior à inicial.';
            }

            $observacao = $this->sanitizar((string) ($colunas[9] ?? '')) ?: null;
            if ($tipo === TipoOcorrenciaFrequencia::OUTRO && ! $observacao) {
                $erros[] = 'Descreva a ocorrência quando o tipo for OUTRO.';
            }

            if ($servidor && $competencia) {
                if ($servidor->setorNaCompetencia($referencia) !== $setorId) {
                    $erros[] = 'O servidor não pertence ao setor nesta competência.';
                }
                if (! $servidor->estaAtivoNaCompetencia($referencia)) {
                    $erros[] = 'O servidor não está ativo no período da competência.';
                }
                $this->validarDatasNoPeriodo($competencia, $dataInicio, $dataFim, $dias, $erros);
            }

            $dados = [
                'matricula' => $matricula,
                'competencia' => $referencia,
                'servidor_id' => $servidor?->id,
                'competencia_id' => $competencia?->id,
                'tipo' => $tipo->value,
                'data_inicio' => $dataInicio?->toDateString(),
                'data_fim' => $dataFim?->toDateString(),
                'dias_especificos' => array_map(fn (Carbon $dia): string => $dia->toDateString(), $dias),
                'justificada' => $this->parseBooleano($colunas[6], 'justificada', true),
                'possui_comprovacao' => $this->parseBooleano($colunas[7], 'possui_comprovacao') ?? false,
                'referencia_documento' => $this->sanitizar((string) ($colunas[8] ?? '')) ?: null,
                'observacao_original' => $observacao,
            ];

            if ($servidor && $competencia && $erros === []) {
                $assinatura = $this->assinatura($dados);
                if (isset($assinaturas[$assinatura])) {
                    $erros[] = 'Já existe uma ocorrência idêntica ou repetida neste arquivo.';
                } else {
                    $assinaturas[$assinatura] = true;
                }
            }
        } catch (InvalidArgumentException $e) {
            $erros[] = $e->getMessage();
        }

        return [
            'numero_linha' => $numeroLinha,
            'conteudo_original' => $conteudo,
            'dados' => $dados,
            'erros' => $erros,
            'valida' => $erros === [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function lerArquivo(string $caminho): array
    {
        if (! is_file($caminho)) {
            throw new Exception('Arquivo de importação não encontrado.');
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false || $linhas === []) {
            throw new InvalidArgumentException('O arquivo não possui conteúdo.');
        }

        $cabecalho = str_getcsv(ltrim(array_shift($linhas), "\xEF\xBB\xBF"), ';', '"', '\\');
        $cabecalho = array_map(fn (string $campo): string => Str::lower(trim(Str::ascii($campo))), $cabecalho);
        if ($cabecalho !== self::CABECALHO) {
            throw new InvalidArgumentException('O cabeçalho não corresponde ao modelo de importação de ocorrências.');
        }
        if ($linhas === []) {
            throw new InvalidArgumentException('O arquivo não possui linhas de dados.');
        }
        if (count($linhas) > 500) {
            throw new InvalidArgumentException('O arquivo CSV excede o limite de 500 linhas.');
        }

        return array_values($linhas);
    }

    /**
     * @param  array<int, string>  $linhas
     * @return array{0: Collection<string, Servidor>, 1: Collection<string, Competencia>, 2: array<string, bool>}
     */
    private function carregarContexto(array $linhas, int $setorId): array
    {
        $matriculas = [];
        $referencias = [];
        foreach ($linhas as $linha) {
            $colunas = str_getcsv($linha, ';', '"', '\\');
            $matriculas[] = trim((string) ($colunas[0] ?? ''));
            $referencias[] = trim((string) ($colunas[1] ?? ''));
        }

        $servidores = Servidor::where('setor_id', $setorId)
            ->whereIn('matricula', array_unique(array_filter($matriculas)))
            ->with('lotacoes')->get()->keyBy('matricula');
        $competencias = Competencia::whereIn('referencia', array_unique(array_filter($referencias)))
            ->get()->keyBy('referencia');

        $assinaturas = [];
        $existentes = OcorrenciaFrequencia::where('setor_id', $setorId)
            ->whereIn('servidor_id', $servidores->pluck('id'))
            ->whereIn('competencia_id', $competencias->pluck('id'))
            ->with('dias')->get();
        foreach ($existentes as $existente) {
            $assinaturas[$this->assinatura([
                'servidor_id' => $existente->servidor_id,
                'competencia_id' => $existente->competencia_id,
                'tipo' => $existente->tipo->value,
                'data_inicio' => $existente->data_inicio?->toDateString(),
                'data_fim' => $existente->data_fim?->toDateString(),
                'dias_especificos' => $existente->dias->pluck('data')->map->toDateString()->all(),
            ])] = true;
        }

        return [$servidores, $competencias, $assinaturas];
    }

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
                'status' => $linha['valida'] ? ImportacaoLinhaStatus::VALIDA : ImportacaoLinhaStatus::INVALIDA,
                'erros' => $linha['erros'] ?: null,
            ]);
        }
    }

    private function atualizarAnaliseInvalida(Importacao $importacao, array $analise): void
    {
        DB::transaction(function () use ($importacao, $analise): void {
            $importacao->linhas()->delete();
            $this->salvarLinhas($importacao, $analise['linhas']);
            $importacao->update([
                'status' => ImportacaoStatus::INVALIDA,
                'linhas_validas' => $analise['validos'],
                'linhas_invalidas' => $analise['invalidos'],
            ]);
        });
    }

    private function parseTipo(?string $valor): TipoOcorrenciaFrequencia
    {
        $normalizado = preg_replace('/[^A-Z0-9]+/', '_', Str::upper(Str::ascii(trim($valor ?? ''))));
        $normalizado = trim((string) $normalizado, '_');

        return TipoOcorrenciaFrequencia::tryFrom($normalizado)
            ?? throw new InvalidArgumentException("Tipo de ocorrência '{$valor}' inválido.");
    }

    private function parseData(?string $valor, string $campo): ?Carbon
    {
        $valor = trim($valor ?? '');
        if ($valor === '') {
            return null;
        }

        $data = Carbon::createFromFormat('!Y-m-d', $valor);
        if (! $data || $data->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException("O campo {$campo} deve usar o formato YYYY-MM-DD.");
        }

        return $data;
    }

    /** @return array<int, Carbon> */
    private function parseDias(?string $valor): array
    {
        $partes = preg_split('/[,|\s]+/', trim($valor ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $dias = [];
        foreach ($partes as $parte) {
            $data = $this->parseData($parte, 'dias_especificos');
            $dias[$data->toDateString()] = $data;
        }

        ksort($dias);

        return array_values($dias);
    }

    private function parseBooleano(?string $valor, string $campo, bool $aceitaVazio = false): ?bool
    {
        $normalizado = Str::upper(Str::ascii(trim($valor ?? '')));
        if ($normalizado === '' && $aceitaVazio) {
            return null;
        }
        if (in_array($normalizado, ['1', 'SIM', 'S', 'TRUE'], true)) {
            return true;
        }
        if (in_array($normalizado, ['0', 'NAO', 'N', 'FALSE', ''], true)) {
            return false;
        }

        throw new InvalidArgumentException("O campo {$campo} deve conter SIM ou NÃO.");
    }

    private function validarDatasNoPeriodo(Competencia $competencia, ?Carbon $inicio, ?Carbon $fim, array $dias, array &$erros): void
    {
        foreach (array_filter([$inicio, $fim, ...$dias]) as $data) {
            if ($data->lt($competencia->inicioPeriodo()) || $data->gt($competencia->fimPeriodo())) {
                $erros[] = "A data {$data->format('d/m/Y')} está fora do período {$competencia->inicioPeriodo()->format('d/m/Y')} a {$competencia->fimPeriodo()->format('d/m/Y')}.";
                break;
            }
        }
    }

    private function assinatura(array $dados): string
    {
        $dias = $dados['dias_especificos'] ?? [];
        sort($dias);

        return implode('|', [
            $dados['servidor_id'], $dados['competencia_id'], $dados['tipo'],
            $dados['data_inicio'] ?? '', $dados['data_fim'] ?? '', implode(',', $dias),
        ]);
    }

    private function sanitizar(string $texto): string
    {
        return trim(strip_tags($texto));
    }
}
