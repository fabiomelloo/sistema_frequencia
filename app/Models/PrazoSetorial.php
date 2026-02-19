<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrazoSetorial extends Model
{
    protected $table = 'prazos_setoriais';

    protected $fillable = [
        'competencia_id',
        'setor_id',
        'data_limite',
        'fechado_em',
        'fechado_por',
    ];

    protected $casts = [
        'data_limite' => 'date',
        'fechado_em' => 'datetime',
    ];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function quemFechou(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    /**
     * Verifica se o setor perdeu o prazo para a competência.
     */
    public static function prazoExpirado(int $competenciaId, int $setorId): bool
    {
        $prazo = self::where('competencia_id', $competenciaId)
            ->where('setor_id', $setorId)
            ->first();

        if (!$prazo) {
            return false; // sem prazo configurado = sem restrição
        }

        return now()->gt($prazo->data_limite) && !$prazo->fechado_em;
    }

    /**
     * Verifica se o setor já fechou (entregou) para a competência.
     */
    public static function setorFechado(int $competenciaId, int $setorId): bool
    {
        return self::where('competencia_id', $competenciaId)
            ->where('setor_id', $setorId)
            ->whereNotNull('fechado_em')
            ->exists();
    }

    /**
     * Retorna o prazo limite para um setor numa competência.
     */
    public static function obterPrazo(int $competenciaId, int $setorId): ?self
    {
        return self::where('competencia_id', $competenciaId)
            ->where('setor_id', $setorId)
            ->first();
    }
}
