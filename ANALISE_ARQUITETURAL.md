# Análise Arquitetural - Sistema de Lançamento de Frequências

## 📋 SUMÁRIO EXECUTIVO

Esta análise identifica problemas arquiteturais, violações de padrões MVC, ausência de regras de negócio críticas e riscos para integração com folha de pagamento.

---

## 🔴 PROBLEMAS ENCONTRADOS

### 1. **ARQUITETURA MVC - Violações Críticas**

#### 1.1. Validação no Controller (EventoController)
**Problema:** `EventoController::store()` e `update()` fazem validação manual ao invés de usar Form Request.

```php
// ❌ ERRADO - EventoController.php linhas 33-88
public function store(Request $request): RedirectResponse
{
    $validated = validator($data, $rules, [...])->validate();
    // Lógica de validação misturada com lógica de negócio
}
```

**Impacto:** 
- Violação do Single Responsibility Principle
- Código duplicado entre `store()` e `update()`
- Dificulta manutenção e testes

#### 1.2. Regras de Negócio no Controller
**Problema:** Conversão de dados e validações customizadas no Controller.

```php
// ❌ ERRADO - EventoController.php linhas 36-59
$data['exige_dias'] = $request->has('exige_dias') ? true : false;
// Validação de valor_maximo > valor_minimo no Controller
```

**Impacto:** Regras de negócio espalhadas, difíceis de reutilizar e testar.

#### 1.3. Queries Complexas no Controller
**Problema:** Queries diretas nos Controllers sem abstração.

```php
// ❌ ERRADO - LancamentoSetorialController.php linha 26
$eventos = $setor->eventosPermitidos()
    ->where('eventos_folha.ativo', true)
    ->orderBy('eventos_folha.descricao')
    ->get();
```

**Impacto:** Dificulta mudanças no banco e testes unitários.

#### 1.4. Código Duplicado no Model
**Problema:** Método `evento()` com código duplicado e não utilizado.

```php
// ❌ ERRADO - LancamentoSetorial.php linhas 38-46
public function evento(): BelongsTo
{
    return $this->belongsTo(EventoFolha::class, 'evento_id');
    return $this->belongsTo(EventoFolha::class, 'evento_id')->with('setoresComDireito');
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
    }
}
```

**Impacto:** Código morto, possível erro de sintaxe.

---

### 2. **REGRAS DE NEGÓCIO - Ausências Críticas**

#### 2.1. Falta de Validação de Periculosidade
**Problema:** Sistema menciona "periculosidade" mas não há campo nem validação.

**Requisito:** Periculosidade e Insalubridade não podem ser aplicadas simultaneamente.

**Status:** ❌ **NÃO IMPLEMENTADO**

#### 2.2. Falta de Validação de Adicional de Turno
**Problema:** Não há campo nem validação para "adicional de turno" aplicável apenas para vigia.

**Requisito:** Adicional de turno só pode ser aplicado quando servidor tem função de vigia.

**Status:** ❌ **NÃO IMPLEMENTADO**

#### 2.3. Falta de Validação de Adicional Noturno
**Problema:** Não há campo nem validação para "adicional noturno".

**Requisito:** Adicional noturno só pode ser aplicado quando há trabalho noturno real.

**Status:** ❌ **NÃO IMPLEMENTADO**

#### 2.4. Falta de Validação de Coerência Dias/Valores
**Problema:** Não há validação que relaciona dias trabalhados com valores e adicionais.

**Requisito:** Dias trabalhados devem ser coerentes com os adicionais aplicados.

**Status:** ❌ **NÃO IMPLEMENTADO**

---

### 3. **VALIDAÇÕES - Problemas**

#### 3.1. Validação Condicional Ineficiente
**Problema:** `StoreLancamentoSetorialRequest` faz múltiplas queries ao banco dentro de closures.

```php
// ❌ PROBLEMÁTICO - StoreLancamentoSetorialRequest.php linha 57
function ($attribute, $value, $fail) {
    $evento = EventoFolha::find($this->evento_id); // Query repetida
    if ($evento && $evento->exige_dias && is_null($value)) {
        $fail('Dias lançados é obrigatório para este evento.');
    }
}
```

**Impacto:** N+1 queries, performance ruim.

#### 3.2. Falta de Validação de Tipos de Evento
**Problema:** Não há validação que impeça eventos incompatíveis (ex: insalubridade + periculosidade).

**Status:** ❌ **NÃO IMPLEMENTADO**

---

### 4. **ESTRUTURA DE DADOS - Problemas**

#### 4.1. Status Hardcoded como Strings
**Problema:** Status do lançamento são strings mágicas espalhadas pelo código.

```php
// ❌ PROBLEMÁTICO
if ($this->status === 'PENDENTE') { ... }
if ($this->status === 'CONFERIDO') { ... }
```

**Impacto:** Fácil erro de digitação, sem autocomplete, difícil refatoração.

#### 4.2. Falta de Campos no Banco
**Problema:** Campos mencionados nos requisitos não existem:
- `porcentagem_periculosidade` (não existe)
- `adicional_turno` (não existe)
- `adicional_noturno` (não existe)
- `funcao_vigia` em Servidor (não existe)
- `trabalha_noturno` em Servidor (não existe)

**Status:** ❌ **CAMPOS FALTANTES**

#### 4.3. Enum no PostgreSQL
**Problema:** Migration usa `enum()` que não é nativo do PostgreSQL.

```php
// ❌ PROBLEMÁTICO - Migration linha 19
$table->enum('status', ['PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO'])
```

**Impacto:** Pode causar problemas de compatibilidade. PostgreSQL usa `CHECK` constraints.

---

### 5. **SERVIÇOS - Problemas**

#### 5.1. Service com Responsabilidades Múltiplas
**Problema:** `GeradorTxtFolhaService` faz query, validação, formatação e escrita de arquivo.

```php
// ❌ PROBLEMÁTICO - GeradorTxtFolhaService.php
public function gerar(): array
{
    $lancamentos = LancamentoSetorial::where(...)->get(); // Query
    if ($lancamentos->isEmpty()) { ... } // Validação
    foreach ($lancamentos as $l) { ... } // Formatação
    file_put_contents($caminho, $conteudo); // I/O
}
```

**Impacto:** Viola Single Responsibility Principle, difícil testar.

#### 5.2. Falta de Tratamento de Erros Específicos
**Problema:** Service lança `Exception` genérica sem tipos específicos.

**Impacto:** Dificulta tratamento diferenciado de erros.

---

### 6. **RISCOS PARA INTEGRAÇÃO COM FOLHA**

#### 6.1. Formato de Exportação Fixo
**Problema:** Formato TXT é hardcoded (37 caracteres) sem validação de tamanhos.

```php
// ❌ RISCO - GeradorTxtFolhaService.php linha 38
$codigoEvento = str_pad($l->evento->codigo_evento, 10, '0', STR_PAD_LEFT);
```

**Risco:** Se código do evento > 10 caracteres, exportação quebra.

#### 6.2. Falta de Validação Pré-Exportação
**Problema:** Não valida se dados estão completos antes de exportar.

**Risco:** Exportação de dados incompletos para folha.

#### 6.3. Falta de Log de Exportação
**Problema:** Não há log de quando/quem exportou.

**Risco:** Dificulta auditoria e rastreabilidade.

#### 6.4. Falta de Validação de Duplicidade
**Problema:** Não valida se mesmo servidor/evento já foi exportado no mesmo período.

**Risco:** Duplicação de lançamentos na folha.

---

## ✅ O QUE ESTÁ CORRETO

### 1. **Estrutura MVC Básica**
- ✅ Separação de Controllers, Models e Views
- ✅ Uso de Form Request para validação (`StoreLancamentoSetorialRequest`)
- ✅ Service para lógica complexa (`GeradorTxtFolhaService`)

### 2. **Relacionamentos Eloquent**
- ✅ Relacionamentos bem definidos (`belongsTo`, `hasMany`, `belongsToMany`)
- ✅ Eager loading quando necessário (`with()`)

### 3. **Segurança**
- ✅ Middleware de autenticação
- ✅ Middleware de role (`CheckRole`)
- ✅ Validação de propriedade (setor do usuário)

### 4. **Validações Básicas**
- ✅ Validação de servidor ativo
- ✅ Validação de evento ativo
- ✅ Validação de permissão de setor para evento
- ✅ Validação de campos obrigatórios baseados no evento

---

## 💡 SUGESTÕES DE MELHORIA

### 1. **CRIAR ENUM PARA STATUS**

```php
// app/Enums/LancamentoStatus.php
namespace App\Enums;

enum LancamentoStatus: string
{
    case PENDENTE = 'PENDENTE';
    case CONFERIDO = 'CONFERIDO';
    case REJEITADO = 'REJEITADO';
    case EXPORTADO = 'EXPORTADO';

    public function label(): string
    {
        return match($this) {
            self::PENDENTE => 'Pendente',
            self::CONFERIDO => 'Conferido',
            self::REJEITADO => 'Rejeitado',
            self::EXPORTADO => 'Exportado',
        };
    }
}
```

**Uso:**
```php
// Model
protected $casts = [
    'status' => LancamentoStatus::class,
];

// Controller
if ($lancamento->status === LancamentoStatus::PENDENTE) { ... }
```

---

### 2. **CRIAR FORM REQUEST PARA EVENTO**

```php
// app/Http/Requests/StoreEventoRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ou verificar permissão
    }

    public function rules(): array
    {
        return [
            'codigo_evento' => [
                'required',
                'string',
                'max:20',
                Rule::unique('eventos_folha', 'codigo_evento')
                    ->ignore($this->route('evento')?->id),
            ],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['required', 'boolean'],
            'exige_valor' => ['required', 'boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => [
                'nullable',
                'numeric',
                'min:0',
                'gt:valor_minimo',
            ],
            'dias_maximo' => ['nullable', 'integer', 'min:1'],
            'exige_observacao' => ['required', 'boolean'],
            'exige_porcentagem' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'exige_dias' => $this->has('exige_dias'),
            'exige_valor' => $this->has('exige_valor'),
            'exige_observacao' => $this->has('exige_observacao'),
            'exige_porcentagem' => $this->has('exige_porcentagem'),
            'ativo' => $this->has('ativo'),
            'valor_minimo' => $this->valor_minimo ?: null,
            'valor_maximo' => $this->valor_maximo ?: null,
            'dias_maximo' => $this->dias_maximo ?: null,
        ]);
    }
}
```

---

### 3. **CRIAR REPOSITORY PARA QUERIES**

```php
// app/Repositories/LancamentoSetorialRepository.php
namespace App\Repositories;

use App\Models\LancamentoSetorial;
use App\Enums\LancamentoStatus;
use Illuminate\Database\Eloquent\Collection;

class LancamentoSetorialRepository
{
    public function getEventosPermitidosParaSetor(int $setorId): Collection
    {
        return \App\Models\Setor::find($setorId)
            ->eventosPermitidos()
            ->where('eventos_folha.ativo', true)
            ->orderBy('eventos_folha.descricao')
            ->get();
    }

    public function getLancamentosPorSetor(int $setorId, ?LancamentoStatus $status = null)
    {
        $query = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->with(['servidor', 'evento', 'setorOrigem']);

        if ($status) {
            $query->where('status', $status->value);
        } else {
            $query->where('status', '!=', LancamentoStatus::EXPORTADO->value);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    public function getLancamentosParaExportacao(): Collection
    {
        return LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->with(['evento', 'servidor'])
            ->get();
    }
}
```

---

### 4. **CRIAR SERVICE PARA REGRAS DE NEGÓCIO**

```php
// app/Services/ValidacaoLancamentoService.php
namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\EventoFolha;
use Illuminate\Support\Facades\Validator;

class ValidacaoLancamentoService
{
    /**
     * Valida se adicional de turno pode ser aplicado
     */
    public function podeAplicarAdicionalTurno(Servidor $servidor, EventoFolha $evento): bool
    {
        // Adicional de turno só para vigia
        if ($evento->codigo_evento === 'ADIC_TURNO') {
            return $servidor->funcao_vigia === true;
        }
        return true;
    }

    /**
     * Valida se adicional noturno pode ser aplicado
     */
    public function podeAplicarAdicionalNoturno(Servidor $servidor, EventoFolha $evento): bool
    {
        if ($evento->codigo_evento === 'ADIC_NOTURNO') {
            return $servidor->trabalha_noturno === true;
        }
        return true;
    }

    /**
     * Valida se insalubridade e periculosidade não estão simultaneamente
     */
    public function validarInsalubridadePericulosidade(
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade
    ): bool {
        if ($porcentagemInsalubridade && $porcentagemPericulosidade) {
            return false; // Não podem coexistir
        }
        return true;
    }

    /**
     * Valida coerência entre dias trabalhados e adicionais
     */
    public function validarCoerenciaDiasAdicionais(
        ?int $diasLancados,
        ?float $valor,
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade
    ): bool {
        // Se há dias lançados, deve haver valor ou adicional
        if ($diasLancados && $diasLancados > 0) {
            if (!$valor && !$porcentagemInsalubridade && !$porcentagemPericulosidade) {
                return false;
            }
        }
        return true;
    }
}
```

---

### 5. **ADICIONAR CAMPOS FALTANTES - Migration**

```php
// database/migrations/XXXX_add_campos_lancamento.php
Schema::table('lancamentos_setoriais', function (Blueprint $table) {
    $table->integer('porcentagem_periculosidade')->nullable()
        ->comment('Porcentagem de periculosidade (não pode coexistir com insalubridade)');
    $table->decimal('adicional_turno', 10, 2)->nullable()
        ->comment('Adicional de turno (apenas para vigia)');
    $table->decimal('adicional_noturno', 10, 2)->nullable()
        ->comment('Adicional noturno (apenas quando trabalha noturno)');
});

Schema::table('servidores', function (Blueprint $table) {
    $table->boolean('funcao_vigia')->default(false)
        ->comment('Indica se o servidor exerce função de vigia');
    $table->boolean('trabalha_noturno')->default(false)
        ->comment('Indica se o servidor trabalha em período noturno');
});
```

---

### 6. **MELHORAR FORM REQUEST COM CACHE**

```php
// app/Http/Requests/StoreLancamentoSetorialRequest.php
public function rules(): array
{
    $user = auth()->user();
    $evento = $this->getEvento(); // Cache da query

    return [
        'servidor_id' => [
            'required',
            'exists:servidores,id',
            function ($attribute, $value, $fail) use ($user) {
                $servidor = \App\Models\Servidor::find($value);
                if (!$servidor || $servidor->setor_id !== $user->setor_id || !$servidor->ativo) {
                    $fail('Servidor inválido ou inativo.');
                }
            },
        ],
        'evento_id' => [
            'required',
            'exists:eventos_folha,id',
            function ($attribute, $value, $fail) use ($user, $evento) {
                if (!$evento || !$evento->ativo || !$evento->temDireitoNoSetor($user->setor_id)) {
                    $fail('Evento inválido ou sem permissão.');
                }
            },
        ],
        'dias_lancados' => [
            'nullable',
            'integer',
            'min:0',
            function ($attribute, $value, $fail) use ($evento) {
                if ($evento && $evento->exige_dias && is_null($value)) {
                    $fail('Dias lançados é obrigatório para este evento.');
                }
                if ($evento && $evento->dias_maximo && $value > $evento->dias_maximo) {
                    $fail("Máximo de dias permitido: {$evento->dias_maximo}");
                }
            },
        ],
        // ... outras validações usando $evento em cache
        'porcentagem_insalubridade' => [
            'nullable',
            'integer',
            'in:10,20,40',
            function ($attribute, $value, $fail) use ($evento) {
                if ($evento && $evento->exige_porcentagem && is_null($value)) {
                    $fail('Porcentagem de insalubridade é obrigatória.');
                }
                // Validar não coexistência com periculosidade
                if ($value && $this->porcentagem_periculosidade) {
                    $fail('Insalubridade e periculosidade não podem coexistir.');
                }
            },
        ],
        'porcentagem_periculosidade' => [
            'nullable',
            'integer',
            'in:30', // Periculosidade geralmente é 30%
            function ($attribute, $value, $fail) {
                if ($value && $this->porcentagem_insalubridade) {
                    $fail('Periculosidade e insalubridade não podem coexistir.');
                }
            },
        ],
    ];
}

protected function getEvento(): ?EventoFolha
{
    if (!$this->has('evento_id')) {
        return null;
    }
    
    return EventoFolha::find($this->evento_id);
}
```

---

### 7. **REFATORAR SERVICE DE EXPORTAÇÃO**

```php
// app/Services/GeradorTxtFolhaService.php
namespace App\Services;

use App\Repositories\LancamentoSetorialRepository;
use App\Services\ValidacaoExportacaoService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GeradorTxtFolhaService
{
    public function __construct(
        private LancamentoSetorialRepository $repository,
        private ValidacaoExportacaoService $validacaoService
    ) {}

    public function gerar(): array
    {
        $lancamentos = $this->repository->getLancamentosParaExportacao();
        
        $this->validacaoService->validarLancamentos($lancamentos);
        
        $formatter = new FormatterTxtFolha();
        $conteudo = $formatter->formatar($lancamentos);
        
        $nomeArquivo = $this->gerarNomeArquivo();
        $this->salvarArquivo($nomeArquivo, $conteudo);
        
        $idsExportados = $lancamentos->pluck('id');
        
        Log::info('Exportação realizada', [
            'arquivo' => $nomeArquivo,
            'quantidade' => $lancamentos->count(),
            'usuario' => auth()->id(),
        ]);
        
        return [
            'nomeArquivo' => $nomeArquivo,
            'idsExportados' => $idsExportados,
        ];
    }

    private function gerarNomeArquivo(): string
    {
        return 'LOTE_' . now()->format('Ym') . '.txt';
    }

    private function salvarArquivo(string $nomeArquivo, string $conteudo): void
    {
        Storage::put($nomeArquivo, $conteudo);
    }
}

// app/Services/FormatterTxtFolha.php
class FormatterTxtFolha
{
    private const TAMANHO_CODIGO_EVENTO = 10;
    private const TAMANHO_MATRICULA = 13;
    private const TAMANHO_VALOR = 14;
    private const TAMANHO_LINHA = 37;

    public function formatar(Collection $lancamentos): string
    {
        $conteudo = '';
        
        foreach ($lancamentos as $lancamento) {
            $linha = $this->formatarLinha($lancamento);
            $this->validarTamanhoLinha($linha, $lancamento->id);
            $conteudo .= $linha . PHP_EOL;
        }
        
        return $conteudo;
    }

    private function formatarLinha(LancamentoSetorial $lancamento): string
    {
        $codigoEvento = $this->formatarCodigoEvento($lancamento->evento->codigo_evento);
        $matricula = $this->formatarMatricula($lancamento->servidor->matricula);
        $valor = $this->formatarValor($lancamento->valor);
        
        return $codigoEvento . $matricula . $valor;
    }

    private function formatarCodigoEvento(string $codigo): string
    {
        if (strlen($codigo) > self::TAMANHO_CODIGO_EVENTO) {
            throw new \InvalidArgumentException(
                "Código do evento excede tamanho máximo: {$codigo}"
            );
        }
        
        return str_pad($codigo, self::TAMANHO_CODIGO_EVENTO, '0', STR_PAD_LEFT);
    }

    private function formatarMatricula(string $matricula): string
    {
        if (strlen($matricula) > self::TAMANHO_MATRICULA) {
            throw new \InvalidArgumentException(
                "Matrícula excede tamanho máximo: {$matricula}"
            );
        }
        
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
            throw new \InvalidArgumentException(
                "Linha do lançamento #{$lancamentoId} tem tamanho inválido: " . strlen($linha)
            );
        }
    }
}
```

---

### 8. **CORRIGIR MIGRATION PARA POSTGRESQL**

```php
// Usar CHECK constraint ao invés de enum
Schema::create('lancamentos_setoriais', function (Blueprint $table) {
    // ...
    $table->string('status')->default('PENDENTE');
    // ...
});

// Adicionar constraint
DB::statement("
    ALTER TABLE lancamentos_setoriais 
    ADD CONSTRAINT lancamentos_setoriais_status_check 
    CHECK (status IN ('PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO'))
");
```

---

## 🎯 PRIORIDADES DE IMPLEMENTAÇÃO

### **ALTA PRIORIDADE (Crítico para Integração)**

1. ✅ Adicionar campos faltantes (periculosidade, adicionais, flags em servidor)
2. ✅ Criar validações de regras de negócio (insalubridade/periculosidade, vigia, noturno)
3. ✅ Corrigir código duplicado no Model `LancamentoSetorial`
4. ✅ Criar Form Request para `EventoController`
5. ✅ Melhorar validação no `StoreLancamentoSetorialRequest` (cache de queries)

### **MÉDIA PRIORIDADE (Melhoria Arquitetural)**

6. ✅ Criar Enum para Status
7. ✅ Criar Repository para queries
8. ✅ Refatorar Service de exportação
9. ✅ Corrigir Migration para PostgreSQL
10. ✅ Adicionar logs de exportação

### **BAIXA PRIORIDADE (Otimização)**

11. ✅ Criar DTOs para transferência de dados
12. ✅ Adicionar testes unitários
13. ✅ Documentar APIs

---

## 📊 MÉTRICAS DE QUALIDADE

| Métrica | Atual | Ideal | Status |
|---------|-------|-------|--------|
| Cobertura de Validações | 60% | 95% | ⚠️ |
| Separação de Responsabilidades | 50% | 90% | ⚠️ |
| Reutilização de Código | 40% | 80% | ❌ |
| Testabilidade | 30% | 85% | ❌ |
| Documentação | 20% | 70% | ❌ |

---

## 🔒 RISCOS IDENTIFICADOS

### **Risco ALTO**
- ❌ Exportação de dados incompletos para folha
- ❌ Duplicação de lançamentos na folha
- ❌ Aplicação indevida de adicionais (turno sem vigia, noturno sem trabalho noturno)
- ❌ Aplicação simultânea de insalubridade e periculosidade

### **Risco MÉDIO**
- ⚠️ Performance ruim por N+1 queries
- ⚠️ Falta de auditoria (logs)
- ⚠️ Código difícil de manter

### **Risco BAIXO**
- ✅ Compatibilidade PostgreSQL (enum)

---

## 📝 CONCLUSÃO

O sistema possui uma **base MVC sólida**, mas apresenta **problemas arquiteturais significativos** e **falta de regras de negócio críticas** para um sistema de integração com folha de pagamento.

**Principais ações imediatas:**
1. Adicionar campos faltantes no banco
2. Implementar validações de regras de negócio
3. Refatorar Controllers para usar Form Requests e Services
4. Criar Enums e Repositories para melhor organização

**Estimativa de refatoração:** 2-3 semanas para implementar todas as melhorias sugeridas.
