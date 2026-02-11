<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Competencia extends Model
{
    protected $table = 'competencias';

    protected $fillable = [
        'referencia',
        'status',
        'data_limite',
        'aberta_por',
        'fechada_por',
        'fechada_em',
    ];

    protected $casts = [
        'data_limite' => 'date',
        'fechada_em' => 'datetime',
    ];

    // Relationships
    public function quemAbriu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberta_por');
    }

    /**
     * Alias para quemAbriu, usado em algumas views.
     */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberta_por');
    }

    public function quemFechou(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechada_por');
    }

    // Status helpers
    public function estaAberta(): bool
    {
        return $this->status === 'ABERTA';
    }

    public function estaFechada(): bool
    {
        return $this->status === 'FECHADA';
    }

    public function prazoExpirado(): bool
    {
        if (!$this->data_limite) {
            return false;
        }
        return now()->gt($this->data_limite);
    }

    public function prazoRestante(): ?int
    {
        if (!$this->data_limite) {
            return null;
        }
        return max(0, (int) now()->diffInDays($this->data_limite, false));
    }

    // Scopes
    public function scopeAberta($query)
    {
        return $query->where('status', 'ABERTA');
    }

    public function scopeFechada($query)
    {
        return $query->where('status', 'FECHADA');
    }

    /**
     * Verifica se uma referência (YYYY-MM) está aberta.
     */
    public static function referenciaAberta(string $referencia): bool
    {
        return self::where('referencia', $referencia)
            ->where('status', 'ABERTA')
            ->exists();
    }

    /**
     * Retorna a competência ativa para a referência, se existir.
     */
    public static function buscarPorReferencia(string $referencia): ?self
    {
        return self::where('referencia', $referencia)->first();
    }

    /**
     * Retorna os dias do mês da competência.
     */
    public function diasNoMes(): int
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->referencia)->daysInMonth;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Retorna os dias úteis do mês (segunda a sexta).
     */
    public function obterDiasUteis(): int
    {
        try {
            $inicio = Carbon::createFromFormat('Y-m', $this->referencia)->startOfMonth();
            $fim = Carbon::createFromFormat('Y-m', $this->referencia)->endOfMonth();
        } catch (\Exception $e) {
            return 0;
        }

        $diasUteis = 0;
        $current = $inicio->copy();

        while ($current->lte($fim)) {
            if (!$current->isWeekend()) {
                $diasUteis++;
            }
            $current->addDay();
        }

        return $diasUteis;
    }
}
