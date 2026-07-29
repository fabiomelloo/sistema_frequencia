<?php

namespace App\Models;

use App\Enums\FrequenciaServidorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FolhaFrequenciaServidor extends Model
{
    use HasFactory;

    protected $table = 'folha_frequencia_servidores';

    protected $fillable = [
        'folha_frequencia_id', 'servidor_id', 'vinculo_funcional_id', 'matricula', 'nome', 'cargo',
        'vinculo', 'carga_horaria', 'designacoes_snapshot', 'mudanca_funcional_no_periodo', 'status', 'observacao_geral',
        'atualizado_por_id', 'preenchida_em',
    ];

    protected $casts = [
        'status' => FrequenciaServidorStatus::class,
        'carga_horaria' => 'integer',
        'designacoes_snapshot' => 'array',
        'mudanca_funcional_no_periodo' => 'boolean',
        'preenchida_em' => 'datetime',
    ];

    public function folha(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequencia::class, 'folha_frequencia_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function vinculoFuncional(): BelongsTo
    {
        return $this->belongsTo(VinculoFuncional::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FolhaFrequenciaItem::class, 'folha_frequencia_servidor_id')->orderBy('descricao');
    }

    public function conferencias(): HasMany
    {
        return $this->hasMany(ConferenciaFrequenciaServidor::class, 'folha_frequencia_servidor_id')->latest('rodada');
    }

    public function conferenciaNaRodada(int $rodada): ?ConferenciaFrequenciaServidor
    {
        if ($this->relationLoaded('conferencias')) {
            return $this->conferencias->firstWhere('rodada', $rodada);
        }

        return $this->conferencias()->where('rodada', $rodada)->first();
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_id');
    }
}
