<?php

namespace App\Models;

use App\Enums\ConferenciaServidorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenciaFrequenciaServidor extends Model
{
    use HasFactory;

    protected $table = 'conferencias_frequencia_servidor';

    protected $fillable = [
        'folha_frequencia_servidor_id',
        'rodada',
        'status',
        'apontamento',
        'conferido_por_id',
        'conferido_em',
    ];

    protected $casts = [
        'rodada' => 'integer',
        'status' => ConferenciaServidorStatus::class,
        'conferido_em' => 'datetime',
    ];

    public function servidorFolha(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequenciaServidor::class, 'folha_frequencia_servidor_id');
    }

    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por_id');
    }
}
