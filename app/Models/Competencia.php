<?php

namespace App\Models;

use App\Enums\CompetenciaStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competencia extends Model
{
    use HasFactory;

    protected $table = 'competencias';

    protected $fillable = [
        'referencia',
        'data_inicio',
        'data_fim',
        'status',
        'data_limite',
        'aberta_por',
        'fechada_por',
        'fechada_em',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_limite' => 'date',
        'fechada_em' => 'datetime',
        'status' => CompetenciaStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Competencia $competencia): void {
            if ($competencia->data_inicio && $competencia->data_fim) {
                return;
            }

            [$inicio, $fim] = self::periodoPadrao($competencia->referencia);
            $competencia->data_inicio ??= $inicio;
            $competencia->data_fim ??= $fim;
        });
    }

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

    public function ocorrenciasFrequencia(): HasMany
    {
        return $this->hasMany(OcorrenciaFrequencia::class);
    }

    public function folhasFrequencia(): HasMany
    {
        return $this->hasMany(FolhaFrequencia::class);
    }

    // Status helpers
    public function estaAberta(): bool
    {
        return $this->status === CompetenciaStatus::ABERTA;
    }

    public function estaFechada(): bool
    {
        return $this->status === CompetenciaStatus::FECHADA;
    }

    public function prazoExpirado(): bool
    {
        if (! $this->data_limite) {
            return false;
        }

        return now()->gt($this->data_limite);
    }

    public function prazoRestante(): ?int
    {
        if (! $this->data_limite) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->data_limite, false));
    }

    // Scopes
    public function scopeAberta($query)
    {
        return $query->where('status', CompetenciaStatus::ABERTA);
    }

    public function scopeFechada($query)
    {
        return $query->where('status', CompetenciaStatus::FECHADA);
    }

    /**
     * Verifica se uma referência (YYYY-MM) está aberta.
     */
    public static function referenciaAberta(string $referencia): bool
    {
        return self::where('referencia', $referencia)
            ->where('status', CompetenciaStatus::ABERTA)
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
     * Alias compatível para a quantidade de dias do período da competência.
     */
    public function diasNoMes(): int
    {
        return $this->diasNoPeriodo();
    }

    public function getDescricaoAttribute(): string
    {
        try {
            $referencia = Carbon::createFromFormat('Y-m-d', "{$this->referencia}-01")->format('m/Y');

            return "{$referencia} ({$this->inicioPeriodo()->format('d/m/Y')} a {$this->fimPeriodo()->format('d/m/Y')})";
        } catch (\Exception $e) {
            return $this->referencia;
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodoPadrao(string $referencia): array
    {
        $base = Carbon::createFromFormat('Y-m-d', "{$referencia}-01")->startOfDay();

        return [
            $base->copy()->subMonthNoOverflow()->day(11),
            $base->copy()->day(10),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodoDaReferencia(string $referencia): array
    {
        $competencia = self::buscarPorReferencia($referencia);

        if ($competencia?->data_inicio && $competencia?->data_fim) {
            return [$competencia->data_inicio->copy(), $competencia->data_fim->copy()];
        }

        return self::periodoPadrao($referencia);
    }

    public function inicioPeriodo(): Carbon
    {
        return $this->data_inicio?->copy() ?? self::periodoPadrao($this->referencia)[0];
    }

    public function fimPeriodo(): Carbon
    {
        return $this->data_fim?->copy() ?? self::periodoPadrao($this->referencia)[1];
    }

    public function diasNoPeriodo(): int
    {
        return $this->inicioPeriodo()->diffInDays($this->fimPeriodo()) + 1;
    }

    /**
     * Retorna os dias úteis do período (segunda a sexta, excluindo feriados).
     * Feriados são lidos da configuração 'feriados_YYYY' (formato: Y-m-d separados por vírgula).
     */
    public static function obterDiasUteis(string $referencia): int
    {
        try {
            [$inicio, $fim] = self::periodoDaReferencia($referencia);
        } catch (\Exception $e) {
            return 0;
        }

        // Carregar feriados do ano a partir da configuração
        $feriados = [];
        foreach (range($inicio->year, $fim->year) as $ano) {
            $feriadosStr = Configuracao::get("feriados_{$ano}", '');
            $feriados = array_merge($feriados, array_filter(array_map('trim', explode(',', $feriadosStr))));
        }

        $diasUteis = 0;
        $current = $inicio->copy();

        while ($current->lte($fim)) {
            if (! $current->isWeekend() && ! in_array($current->format('Y-m-d'), $feriados)) {
                $diasUteis++;
            }
            $current->addDay();
        }

        return $diasUteis;
    }
}
