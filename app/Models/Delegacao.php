<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegacao extends Model
{
    protected $table = 'delegacoes';

    protected $fillable = [
        'delegante_id',
        'delegado_id',
        'setor_id',
        'data_inicio',
        'data_fim',
        'ativa',
        'motivo',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'ativa' => 'boolean',
    ];

    public function delegante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegante_id');
    }

    public function delegado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegado_id');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    // Scopes
    public function scopeAtivas($query)
    {
        return $query->where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now());
    }

    /**
     * Verifica se um user tem delegação ativa para um setor.
     */
    public static function temDelegacaoAtiva(int $userId, int $setorId): bool
    {
        return self::where('delegado_id', $userId)
            ->where('setor_id', $setorId)
            ->where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now())
            ->exists();
    }

    /**
     * Retorna os setores que um user pode acessar por delegação.
     */
    public static function setoresDelegados(int $userId): array
    {
        return self::where('delegado_id', $userId)
            ->where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now())
            ->pluck('setor_id')
            ->toArray();
    }
}
