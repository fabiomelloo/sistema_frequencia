<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LancamentoSetorial extends Model
{
    use SoftDeletes;

    protected $table = 'lancamentos_setoriais';
    protected $fillable = [
        'servidor_id',
        'evento_id',
        'setor_origem_id',
        'competencia',
        'dias_trabalhados',
        'dias_noturnos',
        'valor',
        'valor_gratificacao',
        'porcentagem_insalubridade',
        'porcentagem_periculosidade',
        'adicional_turno',
        'adicional_noturno',
        'observacao',
        // Campos de workflow — NÃO incluir no $fillable (prevenir mass assignment)
        // 'status', 'motivo_rejeicao', 'id_validador', 'validated_at', 'exportado_em'
        // 'conferido_setorial_por', 'conferido_setorial_em'
    ];

    protected $casts = [
        'status' => \App\Enums\LancamentoStatus::class,
        'validated_at' => 'datetime',
        'exportado_em' => 'datetime',
        'conferido_setorial_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'dias_trabalhados' => 'integer',
        'dias_noturnos' => 'integer',
        'valor' => 'decimal:2',
        'valor_gratificacao' => 'decimal:2',
        'porcentagem_insalubridade' => 'integer',
        'porcentagem_periculosidade' => 'integer',
        'adicional_turno' => 'decimal:2',
        'adicional_noturno' => 'decimal:2',
    ];

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

    public function conferidoSetorialPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_setorial_por');
    }

    public function isPendente(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::PENDENTE;
    }

    public function isConferidoSetorial(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::CONFERIDO_SETORIAL;
    }

    public function isConferido(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::CONFERIDO;
    }

    public function isRejeitado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::REJEITADO;
    }

    public function isExportado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::EXPORTADO;
    }

    public function isEstornado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::ESTORNADO;
    }

    public function isCancelado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::CANCELADO;
    }

    public function isEstornoSolicitado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::ESTORNO_SOLICITADO;
    }

    public function podeSerEditado(): bool
    {
        return in_array($this->status, [
            \App\Enums\LancamentoStatus::PENDENTE,
            \App\Enums\LancamentoStatus::REJEITADO,
            \App\Enums\LancamentoStatus::ESTORNADO,
        ]);
    }

    public function podeSerReenviado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::REJEITADO;
    }

    public function podeSerCancelado(): bool
    {
        return in_array($this->status, [
            \App\Enums\LancamentoStatus::PENDENTE,
            \App\Enums\LancamentoStatus::REJEITADO,
        ]);
    }

    public function podeSolicitarEstorno(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::EXPORTADO;
    }

    /**
     * Retorna os dias que o lançamento está pendente de conferência.
     */
    public function diasPendente(): int
    {
        if (!$this->isPendente() && !$this->isConferidoSetorial()) {
            return 0;
        }
        return (int) $this->created_at->diffInDays(now());
    }

    /**
     * Verifica se o SLA está em alerta.
     */
    public function slaEmAlerta(): bool
    {
        $slaDias = \App\Models\Configuracao::getInt('sla_dias_conferencia', 5);
        $alertaDias = \App\Models\Configuracao::getInt('sla_dias_alerta', 3);
        $pendente = $this->diasPendente();
        return $pendente >= $alertaDias && $pendente < $slaDias;
    }

    /**
     * Verifica se o SLA foi ultrapassado.
     */
    public function slaUltrapassado(): bool
    {
        $slaDias = \App\Models\Configuracao::getInt('sla_dias_conferencia', 5);
        return $this->diasPendente() >= $slaDias;
    }

    public function scopeByCompetencia($query, string $competencia)
    {
        return $query->where('competencia', $competencia);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Conta quantas vezes este lançamento foi rejeitado (via audit log).
     */
    public function contarRejeicoes(): int
    {
        return \App\Models\AuditLog::where('modelo', 'LancamentoSetorial')
            ->where('modelo_id', $this->id)
            ->where('acao', 'REJEITOU')
            ->count();
    }

    /**
     * Verifica se atingiu o limite de rejeições configurável.
     */
    public function atingiuLimiteRejeicoes(): bool
    {
        $limite = \App\Models\Configuracao::getInt('limite_rejeicoes_lancamento', 3);
        return $this->contarRejeicoes() >= $limite;
    }

    public function scopeBySetor($query, int $setorId)
    {
        return $query->where('setor_origem_id', $setorId);
    }

    public function scopeByServidor($query, int $servidorId)
    {
        return $query->where('servidor_id', $servidorId);
    }

    public function scopeByEvento($query, int $eventoId)
    {
        return $query->where('evento_id', $eventoId);
    }

    public function scopeSlaUltrapassado($query)
    {
        $slaDias = \App\Models\Configuracao::getInt('sla_dias_conferencia', 5);
        return $query->whereIn('status', [
                \App\Enums\LancamentoStatus::PENDENTE->value,
                \App\Enums\LancamentoStatus::CONFERIDO_SETORIAL->value,
            ])
            ->where('created_at', '<=', now()->subDays($slaDias));
    }

    /**
     * Verifica se já existe lançamento duplicado.
     */
    public static function existeDuplicata(int $servidorId, int $eventoId, string $competencia, ?int $ignorarId = null): bool
    {
        $query = self::where('servidor_id', $servidorId)
            ->where('evento_id', $eventoId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [
            \App\Enums\LancamentoStatus::REJEITADO->value,
            \App\Enums\LancamentoStatus::ESTORNADO->value,
            \App\Enums\LancamentoStatus::CANCELADO->value,
        ]);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->exists();
    }

    /**
     * Soma de dias trabalhados do servidor na competência (exceto o lançamento atual).
     */
    public static function somaDiasServidor(int $servidorId, string $competencia, ?int $ignorarId = null): int
    {
        $query = self::where('servidor_id', $servidorId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [
                \App\Enums\LancamentoStatus::REJEITADO->value,
                \App\Enums\LancamentoStatus::ESTORNADO->value,
                \App\Enums\LancamentoStatus::CANCELADO->value,
            ]);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        return (int) $query->sum('dias_trabalhados');
    }

    /**
     * Verifica incompatibilidade cruzada: servidor já tem lançamento com
     * insalubridade ou periculosidade na mesma competência.
     */
    public static function temIncompatibilidadeCruzada(
        int $servidorId,
        string $competencia,
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade,
        ?int $ignorarId = null
    ): ?string {
        // Se não tem insalubridade nem periculosidade, ok
        if (empty($porcentagemInsalubridade) && empty($porcentagemPericulosidade)) {
            return null;
        }

        $query = self::where('servidor_id', $servidorId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [
                \App\Enums\LancamentoStatus::REJEITADO->value,
                \App\Enums\LancamentoStatus::ESTORNADO->value,
                \App\Enums\LancamentoStatus::CANCELADO->value,
            ]);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        $existentes = $query->get(['porcentagem_insalubridade', 'porcentagem_periculosidade']);

        foreach ($existentes as $existente) {
            if (!empty($porcentagemInsalubridade) && !empty($existente->porcentagem_periculosidade)) {
                return 'Servidor já possui lançamento com periculosidade nesta competência. Insalubridade e periculosidade não podem coexistir.';
            }
            if (!empty($porcentagemPericulosidade) && !empty($existente->porcentagem_insalubridade)) {
                return 'Servidor já possui lançamento com insalubridade nesta competência. Periculosidade e insalubridade não podem coexistir.';
            }
        }

        return null;
    }

    /**
     * Scope de filtragem reutilizável para controllers.
     * Aceita array com chaves: competencia, status, setor_id, servidor_id, evento_id, busca
     */
    public function scopeFiltrar($query, array $filtros): void
    {
        if (!empty($filtros['competencia'])) {
            $query->where('competencia', $filtros['competencia']);
        }

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (!empty($filtros['setor_id'])) {
            $query->where('setor_origem_id', $filtros['setor_id']);
        }

        if (!empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (!empty($filtros['evento_id'])) {
            $query->where('evento_id', $filtros['evento_id']);
        }

        if (!empty($filtros['busca'])) {
            $busca = addcslashes($filtros['busca'], '%_');
            $query->whereHas('servidor', function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('matricula', 'like', "%{$busca}%");
            });
        }
    }
}
