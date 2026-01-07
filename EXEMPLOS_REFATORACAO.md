# Exemplos de Código Refatorado

## 1. Enum para Status de Lançamento

```php
// app/Enums/LancamentoStatus.php
<?php

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

    public function cor(): string
    {
        return match($this) {
            self::PENDENTE => 'warning',
            self::CONFERIDO => 'success',
            self::REJEITADO => 'danger',
            self::EXPORTADO => 'info',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Uso no Model:**
```php
// app/Models/LancamentoSetorial.php
use App\Enums\LancamentoStatus;

protected $casts = [
    'status' => LancamentoStatus::class,
    // ...
];

public function isPendente(): bool
{
    return $this->status === LancamentoStatus::PENDENTE;
}
```

---

## 2. Form Request para Evento (Refatorado)

```php
// app/Http/Requests/StoreEventoRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isCentral() ?? false;
    }

    public function rules(): array
    {
        $eventoId = $this->route('evento')?->id;

        return [
            'codigo_evento' => [
                'required',
                'string',
                'max:20',
                Rule::unique('eventos_folha', 'codigo_evento')->ignore($eventoId),
            ],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['required', 'boolean'],
            'exige_valor' => ['required', 'boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($value && $this->valor_minimo && $value <= $this->valor_minimo) {
                        $fail('O valor máximo deve ser maior que o valor mínimo.');
                    }
                },
            ],
            'dias_maximo' => ['nullable', 'integer', 'min:1'],
            'exige_observacao' => ['required', 'boolean'],
            'exige_porcentagem' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
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

    public function messages(): array
    {
        return [
            'codigo_evento.required' => 'O código do evento é obrigatório.',
            'codigo_evento.unique' => 'Este código de evento já está em uso.',
            'descricao.required' => 'A descrição é obrigatória.',
            'valor_minimo.numeric' => 'O valor mínimo deve ser um número.',
            'valor_maximo.numeric' => 'O valor máximo deve ser um número.',
            'dias_maximo.integer' => 'O dias máximo deve ser um número inteiro.',
            'dias_maximo.min' => 'O dias máximo deve ser pelo menos 1.',
        ];
    }
}
```

**Controller Refatorado:**
```php
// app/Http/Controllers/EventoController.php
public function store(StoreEventoRequest $request): RedirectResponse
{
    $evento = EventoFolha::create($request->validated());

    return redirect()
        ->route('admin.eventos.index')
        ->with('success', 'Evento criado com sucesso!');
}

public function update(StoreEventoRequest $request, EventoFolha $evento): RedirectResponse
{
    $evento->update($request->validated());

    return redirect()
        ->route('admin.eventos.index')
        ->with('success', 'Evento atualizado com sucesso!');
}
```

---

## 3. Repository para Queries

```php
// app/Repositories/LancamentoSetorialRepository.php
<?php

namespace App\Repositories;

use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Enums\LancamentoStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LancamentoSetorialRepository
{
    public function getEventosPermitidosParaSetor(int $setorId): Collection
    {
        return Setor::find($setorId)
            ->eventosPermitidos()
            ->where('eventos_folha.ativo', true)
            ->orderBy('eventos_folha.descricao')
            ->get();
    }

    public function getLancamentosPorSetor(
        int $setorId,
        ?LancamentoStatus $status = null
    ): LengthAwarePaginator {
        $query = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->with(['servidor', 'evento', 'setorOrigem']);

        if ($status) {
            $query->where('status', $status->value);
        } else {
            $query->where('status', '!=', LancamentoStatus::EXPORTADO->value);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    public function getLancamentosParaConferencia(?LancamentoStatus $status = null): LengthAwarePaginator
    {
        $query = LancamentoSetorial::with(['servidor', 'evento', 'setorOrigem', 'validador']);

        if ($status) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('created_at', 'asc')->paginate(15);
    }

    public function getLancamentosParaExportacao(): Collection
    {
        return LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->with(['evento', 'servidor'])
            ->get();
    }

    public function contarPorStatus(): array
    {
        return [
            LancamentoStatus::PENDENTE->value => LancamentoSetorial::where('status', LancamentoStatus::PENDENTE->value)->count(),
            LancamentoStatus::CONFERIDO->value => LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO->value)->count(),
            LancamentoStatus::REJEITADO->value => LancamentoSetorial::where('status', LancamentoStatus::REJEITADO->value)->count(),
            LancamentoStatus::EXPORTADO->value => LancamentoSetorial::where('status', LancamentoStatus::EXPORTADO->value)->count(),
        ];
    }
}
```

**Uso no Controller:**
```php
// app/Http/Controllers/LancamentoSetorialController.php
use App\Repositories\LancamentoSetorialRepository;

public function __construct(
    private LancamentoSetorialRepository $repository
) {}

public function create(): View
{
    $setor = auth()->user()->setor;
    
    return view('lancamentos.create', [
        'servidores' => Servidor::where('setor_id', $setor->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(),
        'eventos' => $this->repository->getEventosPermitidosParaSetor($setor->id),
    ]);
}

public function index(): View
{
    $lancamentos = $this->repository->getLancamentosPorSetor(
        auth()->user()->setor_id
    );

    return view('lancamentos.index', [
        'lancamentos' => $lancamentos,
    ]);
}
```

---

## 4. Service para Validação de Regras de Negócio

```php
// app/Services/ValidacaoLancamentoService.php
<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\EventoFolha;
use Illuminate\Support\Facades\Validator;

class ValidacaoLancamentoService
{
    /**
     * Valida todas as regras de negócio para um lançamento
     */
    public function validarRegrasNegocio(array $data): array
    {
        $errors = [];

        $servidor = Servidor::find($data['servidor_id']);
        $evento = EventoFolha::find($data['evento_id']);

        if (!$servidor || !$evento) {
            return ['servidor_id' => ['Dados inválidos.']];
        }

        // Validar adicional de turno
        if (isset($data['adicional_turno']) && $data['adicional_turno'] > 0) {
            if (!$this->podeAplicarAdicionalTurno($servidor, $evento)) {
                $errors['adicional_turno'] = ['Adicional de turno só pode ser aplicado para servidores com função de vigia.'];
            }
        }

        // Validar adicional noturno
        if (isset($data['adicional_noturno']) && $data['adicional_noturno'] > 0) {
            if (!$this->podeAplicarAdicionalNoturno($servidor, $evento)) {
                $errors['adicional_noturno'] = ['Adicional noturno só pode ser aplicado quando há trabalho noturno real.'];
            }
        }

        // Validar insalubridade e periculosidade
        if (!$this->validarInsalubridadePericulosidade(
            $data['porcentagem_insalubridade'] ?? null,
            $data['porcentagem_periculosidade'] ?? null
        )) {
            $errors['porcentagem_insalubridade'] = ['Insalubridade e periculosidade não podem coexistir.'];
            $errors['porcentagem_periculosidade'] = ['Periculosidade e insalubridade não podem coexistir.'];
        }

        // Validar coerência dias/adicionais
        if (!$this->validarCoerenciaDiasAdicionais(
            $data['dias_lancados'] ?? null,
            $data['valor'] ?? null,
            $data['porcentagem_insalubridade'] ?? null,
            $data['porcentagem_periculosidade'] ?? null,
            $data['adicional_turno'] ?? null,
            $data['adicional_noturno'] ?? null
        )) {
            $errors['dias_lancados'] = ['Dias trabalhados devem estar coerentes com os adicionais aplicados.'];
        }

        return $errors;
    }

    public function podeAplicarAdicionalTurno(Servidor $servidor, EventoFolha $evento): bool
    {
        // Verificar se evento é de adicional de turno
        $codigosAdicionalTurno = ['ADIC_TURNO', 'GRAT_TURNO'];
        
        if (!in_array($evento->codigo_evento, $codigosAdicionalTurno)) {
            return true; // Não é evento de adicional de turno, não precisa validar
        }

        return $servidor->funcao_vigia === true;
    }

    public function podeAplicarAdicionalNoturno(Servidor $servidor, EventoFolha $evento): bool
    {
        $codigosAdicionalNoturno = ['ADIC_NOTURNO', 'GRAT_NOTURNO'];
        
        if (!in_array($evento->codigo_evento, $codigosAdicionalNoturno)) {
            return true;
        }

        return $servidor->trabalha_noturno === true;
    }

    public function validarInsalubridadePericulosidade(
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade
    ): bool {
        // Não podem coexistir
        if ($porcentagemInsalubridade && $porcentagemPericulosidade) {
            return false;
        }
        return true;
    }

    public function validarCoerenciaDiasAdicionais(
        ?int $diasLancados,
        ?float $valor,
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade,
        ?float $adicionalTurno,
        ?float $adicionalNoturno
    ): bool {
        // Se há dias lançados, deve haver pelo menos um valor ou adicional
        if ($diasLancados && $diasLancados > 0) {
            $temValorOuAdicional = $valor 
                || $porcentagemInsalubridade 
                || $porcentagemPericulosidade
                || $adicionalTurno
                || $adicionalNoturno;

            if (!$temValorOuAdicional) {
                return false;
            }
        }

        return true;
    }
}
```

**Uso no Form Request:**
```php
// app/Http/Requests/StoreLancamentoSetorialRequest.php
use App\Services\ValidacaoLancamentoService;

public function rules(): array
{
    $user = auth()->user();
    $evento = $this->getEvento();

    $rules = [
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
        // ... outras regras básicas
    ];

    // Adicionar validação de regras de negócio
    $validacaoService = app(ValidacaoLancamentoService::class);
    $errosNegocio = $validacaoService->validarRegrasNegocio($this->all());

    if (!empty($errosNegocio)) {
        foreach ($errosNegocio as $campo => $mensagens) {
            $rules[$campo][] = function ($attribute, $value, $fail) use ($mensagens) {
                $fail($mensagens[0]);
            };
        }
    }

    return $rules;
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

## 5. Model Refatorado (Corrigindo código duplicado)

```php
// app/Models/LancamentoSetorial.php
<?php

namespace App\Models;

use App\Enums\LancamentoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LancamentoSetorial extends Model
{
    protected $table = 'lancamentos_setoriais';
    
    protected $fillable = [
        'servidor_id',
        'evento_id',
        'setor_origem_id',
        'dias_lancados',
        'valor',
        'porcentagem_insalubridade',
        'porcentagem_periculosidade',
        'adicional_turno',
        'adicional_noturno',
        'observacao',
        'status',
        'motivo_rejeicao',
        'id_validador',
        'validated_at',
        'exportado_em'
    ];

    protected $casts = [
        'status' => LancamentoStatus::class,
        'validated_at' => 'datetime',
        'exportado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dias_lancados' => 'integer',
        'valor' => 'decimal:2',
        'porcentagem_insalubridade' => 'integer',
        'porcentagem_periculosidade' => 'integer',
        'adicional_turno' => 'decimal:2',
        'adicional_noturno' => 'decimal:2',
    ];

    // Relacionamentos
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
    }

    public function setorOrigem(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_validador');
    }

    // Métodos de status (usando Enum)
    public function isPendente(): bool
    {
        return $this->status === LancamentoStatus::PENDENTE;
    }

    public function isConferido(): bool
    {
        return $this->status === LancamentoStatus::CONFERIDO;
    }

    public function isRejeitado(): bool
    {
        return $this->status === LancamentoStatus::REJEITADO;
    }

    public function isExportado(): bool
    {
        return $this->status === LancamentoStatus::EXPORTADO;
    }

    public function podeSerEditado(): bool
    {
        return $this->status === LancamentoStatus::PENDENTE;
    }

    // Scopes
    public function scopePendentes($query)
    {
        return $query->where('status', LancamentoStatus::PENDENTE->value);
    }

    public function scopeConferidos($query)
    {
        return $query->where('status', LancamentoStatus::CONFERIDO->value);
    }

    public function scopeParaExportacao($query)
    {
        return $query->where('status', LancamentoStatus::CONFERIDO->value)
            ->whereNotNull('valor')
            ->where('valor', '>', 0);
    }
}
```

---

## 6. Controller Refatorado (PainelConferenciaController)

```php
// app/Http/Controllers/PainelConferenciaController.php
<?php

namespace App\Http\Controllers;

use App\Repositories\LancamentoSetorialRepository;
use App\Services\GeradorTxtFolhaService;
use App\Http\Requests\RejeitarLancamentoRequest;
use App\Enums\LancamentoStatus;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PainelConferenciaController extends Controller
{
    public function __construct(
        private LancamentoSetorialRepository $repository
    ) {}

    public function index(): View
    {
        $status = $this->validarStatus(request('status', LancamentoStatus::PENDENTE->value));
        
        $lancamentos = $this->repository->getLancamentosParaConferencia(
            LancamentoStatus::from($status)
        );

        $contadores = $this->repository->contarPorStatus();

        return view('painel.index', [
            'lancamentos' => $lancamentos,
            'statusAtual' => $status,
            'contadores' => $contadores,
        ]);
    }

    public function show(LancamentoSetorial $lancamento): View
    {
        return view('painel.show', [
            'lancamento' => $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador']),
        ]);
    }

    public function aprovar(LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isPendente()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos com status PENDENTE podem ser aprovados.']);
        }

        $lancamento->update([
            'status' => LancamentoStatus::CONFERIDO,
            'id_validador' => auth()->id(),
            'validated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Lançamento aprovado com sucesso!');
    }

    public function rejeitar(RejeitarLancamentoRequest $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isPendente()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos com status PENDENTE podem ser rejeitados.']);
        }

        $lancamento->update([
            'status' => LancamentoStatus::REJEITADO,
            'motivo_rejeicao' => $request->validated()['motivo_rejeicao'],
            'id_validador' => auth()->id(),
            'validated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Lançamento rejeitado com sucesso!');
    }

    public function exportar(): Response|RedirectResponse
    {
        try {
            $servico = app(GeradorTxtFolhaService::class);
            $resultado = $servico->gerar();

            // Marcar como exportado
            LancamentoSetorial::whereIn('id', $resultado['idsExportados'])
                ->update([
                    'status' => LancamentoStatus::EXPORTADO,
                    'exportado_em' => now(),
                ]);

            return response()
                ->download(storage_path("app/{$resultado['nomeArquivo']}"))
                ->deleteFileAfterSend(true);
                
        } catch (\Exception $e) {
            \Log::error('Erro ao exportar lançamentos', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('painel.index')
                ->withErrors(['error' => 'Erro ao exportar lançamentos: ' . $e->getMessage()]);
        }
    }

    private function validarStatus(string $status): string
    {
        $statusValidos = LancamentoStatus::valores();
        
        if (!in_array($status, $statusValidos)) {
            return LancamentoStatus::PENDENTE->value;
        }

        return $status;
    }
}
```

---

## 7. Migration Corrigida para PostgreSQL

```php
// database/migrations/XXXX_create_lancamentos_setoriais_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos_setoriais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('evento_id')->constrained('eventos_folha');
            $table->foreignId('setor_origem_id')->constrained('setores');
            $table->integer('dias_lancados')->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            $table->integer('porcentagem_insalubridade')->nullable();
            $table->integer('porcentagem_periculosidade')->nullable();
            $table->decimal('adicional_turno', 10, 2)->nullable();
            $table->decimal('adicional_noturno', 10, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->string('status')->default('PENDENTE');
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('id_validador')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('exportado_em')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('servidor_id');
            $table->index('evento_id');
            $table->index('setor_origem_id');
        });

        // Adicionar constraint CHECK para PostgreSQL
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT lancamentos_setoriais_status_check 
            CHECK (status IN ('PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO'))
        );

        // Constraint para evitar insalubridade e periculosidade simultâneas
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT lancamentos_setoriais_insalubridade_periculosidade_check 
            CHECK (
                (porcentagem_insalubridade IS NULL OR porcentagem_periculosidade IS NULL)
                OR (porcentagem_insalubridade IS NULL AND porcentagem_periculosidade IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos_setoriais');
    }
};
```

---

## 8. Migration para Adicionar Campos em Servidores

```php
// database/migrations/XXXX_add_campos_servidores.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->boolean('funcao_vigia')->default(false)
                ->after('ativo')
                ->comment('Indica se o servidor exerce função de vigia');
            $table->boolean('trabalha_noturno')->default(false)
                ->after('funcao_vigia')
                ->comment('Indica se o servidor trabalha em período noturno');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn(['funcao_vigia', 'trabalha_noturno']);
        });
    }
};
```

---

Estes exemplos mostram como implementar as melhorias sugeridas na análise arquitetural.
