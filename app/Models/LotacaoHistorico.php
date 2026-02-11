<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LotacaoHistorico extends Model
{
    protected $table = 'lotacao_historico';

    protected $fillable = [
        'servidor_id',
        'setor_id',
        'data_inicio',
        'data_fim',
        'observacao',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    /**
     * Retorna o setor em que o servidor estava numa competência (YYYY-MM).
     */
    public static function setorNaCompetencia(int $servidorId, string $competencia): ?int
    {
        $inicioMes = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();

        $lotacao = self::where('servidor_id', $servidorId)
            ->where('data_inicio', '<=', $fimMes)
            ->where(function ($q) use ($inicioMes) {
                $q->whereNull('data_fim')
                  ->orWhere('data_fim', '>=', $inicioMes);
            })
            ->orderBy('data_inicio', 'desc')
            ->first();

        return $lotacao?->setor_id;
    }
}
