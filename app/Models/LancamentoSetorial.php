<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LancamentoSetorial extends Model
{
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
    ];

    protected $casts = [
        'status' => \App\Enums\LancamentoStatus::class,
        'validated_at' => 'datetime',
        'exportado_em' => 'datetime',
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

    // Status helpers
    public function isPendente(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::PENDENTE;
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

    public function podeSerEditado(): bool
    {
        return in_array($this->status, [
            \App\Enums\LancamentoStatus::PENDENTE,
            \App\Enums\LancamentoStatus::REJEITADO,
        ]);
    }

    public function podeSerReenviado(): bool
    {
        return $this->status === \App\Enums\LancamentoStatus::REJEITADO;
    }

    // Scopes para filtragem
    public function scopeByCompetencia($query, string $competencia)
    {
        return $query->where('competencia', $competencia);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
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

    /**
     * Verifica se já existe lançamento duplicado.
     */
    public static function existeDuplicata(int $servidorId, int $eventoId, string $competencia, ?int $ignorarId = null): bool
    {
        $query = self::where('servidor_id', $servidorId)
            ->where('evento_id', $eventoId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [\App\Enums\LancamentoStatus::REJEITADO->value]);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->exists();
    }
}
