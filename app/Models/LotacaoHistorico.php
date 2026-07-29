<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotacaoHistorico extends Model
{
    use HasFactory;

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
     * Retorna o setor em que o servidor estava no período da competência (YYYY-MM).
     */
    public static function setorNaCompetencia(int $servidorId, string $competencia): ?int
    {
        [$inicioPeriodo, $fimPeriodo] = Competencia::periodoDaReferencia($competencia);

        $lotacao = self::where('servidor_id', $servidorId)
            ->where('data_inicio', '<=', $fimPeriodo)
            ->where(function ($q) use ($inicioPeriodo) {
                $q->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $inicioPeriodo);
            })
            ->orderBy('data_inicio', 'desc')
            ->first();

        return $lotacao?->setor_id;
    }
}
