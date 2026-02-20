<?php



use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Models\Competencia;
use App\Enums\LancamentoStatus;
use App\Services\RegrasLancamentoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;
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
     * Formato esperado (12 colunas):
     * matricula;codigo_evento;competencia;dias_trabalhados;dias_noturnos;valor;valor_gratificacao;porcentagem_insalubridade;porcentagem_periculosidade;adicional_turno;adicional_noturno;observacao
     */
    /**
     * Importa lançamentos de um arquivo CSV (Otimizado).
     *
     * @param string $caminhoArquivo
     * @param int $setorId
     * @return array ['importados' => int, 'erros' => array]
     */
    public function importarCsv(string $caminhoArquivo, int $setorId): array
    {
        if (!file_exists($caminhoArquivo)) {
            throw new Exception("Arquivo não encontrado: {$caminhoArquivo}");
        }

        $linhas = file($caminhoArquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Remover header se existir
        $primeiraLinha = trim($linhas[0] ?? '');
        if (stripos($primeiraLinha, 'matricula') !== false || stripos($primeiraLinha, 'matrícula') !== false) {
            array_shift($linhas);
        }

        // limite de linhas
        $limiteLinhas = 500;
        if (count($linhas) > $limiteLinhas) {
            throw new InvalidArgumentException(
                "O arquivo CSV contém " . count($linhas) . " linhas, excedendo o limite de {$limiteLinhas}."
            );
        }

        // 1. Coleta de chaves para Eager Loading
        $matriculas = [];
        $codigosEvento = [];
        $competencias = []; // Normalmente é uma só, mas coletamos todas por segurança

        foreach ($linhas as $l) {
            $colunas = explode(';', $l);
            if (count($colunas) >= 3) { // matricula;codigo;competencia
                $matricula = trim($colunas[0]);
                $codigo = trim($colunas[1]);
                $comp = trim($colunas[2]);
                
                if ($matricula) $matriculas[] = $matricula;
                if ($codigo) $codigosEvento[] = $codigo;
                if ($comp) $competencias[$comp] = true;
            }
        }

        $matriculas = array_unique($matriculas);
        $codigosEvento = array_unique($codigosEvento);
        $competencias = array_keys($competencias);

        // 2. Carregamento em Massa (Eager Loading)
        $servidores = Servidor::whereIn('matricula', $matriculas)
            ->where('setor_id', $setorId) // Adicionado filtro por setor_id para otimização e validação
            ->with(['lotacoes' => function($q) {
                $q->orderBy('data_inicio', 'desc');
            }])
            ->get()
            ->keyBy('matricula');

        $eventos = EventoFolha::whereIn('codigo_evento', $codigosEvento)
            ->where('ativo', true) // Adicionado filtro por ativo para otimização e validação
            ->with(['setoresComDireito' => function($q) use ($setorId) {
                $q->where('setor_id', $setorId);
            }])
            ->get()
            ->keyBy('codigo_evento');

        // Carregar duplicatas existentes para as competências
        // Chave: "servidor_id-evento_id-competencia"
        $duplicatasMap = [];
        if (!empty($matriculas) && !empty($competencias)) {
            $servidorIds = $servidores->pluck('id')->toArray();
            $eventoIds = $eventos->pluck('id')->toArray();

            if (!empty($servidorIds) && !empty($eventoIds)) { // Only query if there are valid IDs
                $lancamentosExistentes = LancamentoSetorial::whereIn('servidor_id', $servidorIds)
                    ->whereIn('evento_id', $eventoIds)
                    ->whereIn('competencia', $competencias)
                    ->whereNotIn('status', [
                        \App\Enums\LancamentoStatus::REJEITADO->value,
                        \App\Enums\LancamentoStatus::ESTORNADO->value
                    ])
                    ->get(['servidor_id', 'evento_id', 'competencia']);

                foreach ($lancamentosExistentes as $exists) {
                    $key = "{$exists->servidor_id}-{$exists->evento_id}-{$exists->competencia}";
                    $duplicatasMap[$key] = true;
                }
            }
        }

        $importados = 0;
        $erros = [];
        $linha = 1; // Ajustado para considerar header removido na contagem real se necessário, mas mantendo 1-based

        // 3. Processamento com Transação
        DB::beginTransaction();
        try {
            foreach ($linhas as $conteudoLinha) {
                $linha++; // Começa na 2 se teve header, ou 1 + offset
                $colunas = str_getcsv($conteudoLinha, ';'); // Usar str_getcsv para melhor tratamento de campos com delimitador

                // Validação básica de colunas (mesma lógica anterior)
                if (count($colunas) < 4) { // Mínimo de campos para matricula;codigo_evento;competencia;dias_trabalhados
                    $erros[] = "Linha {$linha}: número insuficiente de campos (mínimo: 4).";
                    continue;
                }
                
                // Pad to 12 columns to avoid undefined offset errors
                $colunas = array_pad($colunas, 12, null);

                $matricula = trim($colunas[0]);
                $codigoEvento = trim($colunas[1]);
                $competencia = trim($colunas[2]);
                
                // Extração de dados (mesma do original, mas com parse helpers)
                $diasTrabalhados = $this->parseNumero($colunas[3] ?? null);
                $diasNoturnos = $this->parseNumero($colunas[4] ?? null);
                $valor = $this->parseValor($colunas[5] ?? null);
                $valorGratificacao = $this->parseValor($colunas[6] ?? null);
                $porcentagemInsalubridade = $this->parseNumero($colunas[7] ?? null);
                $porcentagemPericulosidade = $this->parseNumero($colunas[8] ?? null);
                $adicionalTurno = $this->parseValor($colunas[9] ?? null);
                $adicionalNoturno = $this->parseValor($colunas[10] ?? null);
                $observacao = $this->sanitizarTexto(trim($colunas[11] ?? '')) ?: null;

                // --- Validações em Memória ---

                if (!isset($servidores[$matricula])) {
                    $erros[] = "Linha {$linha}: Servidor com matrícula '{$matricula}' não encontrado no setor {$setorId}.";
                    continue;
                }
                $servidor = $servidores[$matricula];

                if (!isset($eventos[$codigoEvento])) {
                    $erros[] = "Linha {$linha}: Evento '{$codigoEvento}' não encontrado ou inativo.";
                    continue;
                }
                $evento = $eventos[$codigoEvento];

                if (!$evento->setoresComDireito->contains('id', $setorId)) {
                    $erros[] = "Linha {$linha}: o setor não possui direito ao evento '{$evento->descricao}'.";
                    continue;
                }

                // Validação de competência regex
                if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
                    $erros[] = "Linha {$linha}: competência '{$competencia}' inválida (esperado YYYY-MM).";
                    continue;
                }

                // Checagem de Duplicata (Memória)
                $dupeKey = "{$servidor->id}-{$evento->id}-{$competencia}";
                if (isset($duplicatasMap[$dupeKey])) {
                    $erros[] = "Linha {$linha}: já existe lançamento para {$servidor->nome} / {$evento->descricao} na competência {$competencia}.";
                    continue;
                }

                // Regras complexas (Serviço) - ainda faz queries, mas otimizamos o resto
                try {
                    $dadosValidacao = [
                        'competencia' => $competencia,
                        'dias_trabalhados' => $diasTrabalhados,
                        'dias_noturnos' => $diasNoturnos,
                        'valor' => $valor,
                        'valor_gratificacao' => $valorGratificacao,
                        'porcentagem_insalubridade' => $porcentagemInsalubridade,
                        'porcentagem_periculosidade' => $porcentagemPericulosidade,
                        'adicional_turno' => $adicionalTurno,
                        'adicional_noturno' => $adicionalNoturno,
                        'observacao' => $observacao,
                    ];
                    $this->regrasService->validar($servidor, $evento, $dadosValidacao);
                } catch (InvalidArgumentException $e) {
                    $erros[] = "Linha {$linha}: {$e->getMessage()}";
                    continue;
                }

                // Determinar Setor Histórico (Memória)
                // usar lotação histórica
                $setorOrigemId = $this->obterSetorNaCompetencia($servidor, $competencia) ?? $servidor->setor_id;

                // Persistência
                LancamentoSetorial::create([
                    'servidor_id' => $servidor->id,
                    'evento_id' => $evento->id,
                    'setor_origem_id' => $setorOrigemId,
                    'competencia' => $competencia,
                    'dias_trabalhados' => $diasTrabalhados,
                    'dias_noturnos' => $diasNoturnos,
                    'valor' => $valor,
                    'valor_gratificacao' => $valorGratificacao,
                    'porcentagem_insalubridade' => $porcentagemInsalubridade,
                    'porcentagem_periculosidade' => $porcentagemPericulosidade,
                    'adicional_turno' => $adicionalTurno,
                    'adicional_noturno' => $adicionalNoturno,
                    'observacao' => $observacao ?: "Importado via CSV",
                    'status' => \App\Enums\LancamentoStatus::PENDENTE
                ]);

                // Marca como duplicado para não permitir 2x no mesmo CSV
                $duplicatasMap[$dupeKey] = true;
                $importados++;
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Erro na importação CSV (rollback realizado): " . $e->getMessage());
            throw $e; // Re-throw para o controller tratar
        }

        return [
            'importados' => $importados,
            'erros' => $erros,
            'total_linhas' => count($linhas),
        ];
    }

    /**
     * Helper para determinar setor histórico sem query N+1
     * Usa a collection 'lotacoes' já carregada no servidor.
     */
    private function obterSetorNaCompetencia(Servidor $servidor, string $competencia): ?int
    {
        if ($servidor->lotacoes->isEmpty()) {
            return null;
        }

        $inicioMes = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();

        // Lógica replicada de LotacaoHistorico::setorNaCompetencia, mas em memória
        $lotacao = $servidor->lotacoes->first(function ($l) use ($inicioMes, $fimMes) {
            // data_inicio <= fimMes AND (data_fim null OR data_fim >= inicioMes)
            $inicio = $l->data_inicio instanceof Carbon ? $l->data_inicio : Carbon::parse($l->data_inicio);
            $fim = $l->data_fim ? ($l->data_fim instanceof Carbon ? $l->data_fim : Carbon::parse($l->data_fim)) : null;

            return $inicio->lte($fimMes) && ($fim === null || $fim->gte($inicioMes));
        });

        return $lotacao?->setor_id;
    }

    /**
     * Sanitiza texto para prevenir XSS
     */
    private function sanitizarTexto(string $texto): string
    {
        return htmlspecialchars(strip_tags($texto), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Converte string para número inteiro ou float, retornando null se vazio.
     */
    private function parseNumero(?string $val): ?int
    {
        $val = trim($val ?? '');
        if ($val === '') {
            return null;
        }
        return (int) $val;
    }

    /**
     * Converte string para float (considerando vírgula como separador decimal), retornando null se vazio.
     */
    private function parseValor(?string $val): ?float
    {
        $val = trim($val ?? '');
        if ($val === '') {
            return null;
        }
        return (float) str_replace(',', '.', $val);
    }
}

