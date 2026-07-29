<?php

namespace App\Models;

use App\Enums\TipoOcorrenciaFrequencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OcorrenciaFrequencia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ocorrencias_frequencia';

    protected $fillable = [
        'servidor_id',
        'setor_id',
        'competencia_id',
        'tipo',
        'data_inicio',
        'data_fim',
        'justificada',
        'possui_comprovacao',
        'referencia_documento',
        'observacao_original',
        'origem',
        'importacao_linha_id',
        'criado_por_id',
    ];

    protected $casts = [
        'tipo' => TipoOcorrenciaFrequencia::class,
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'justificada' => 'boolean',
        'possui_comprovacao' => 'boolean',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function dias(): HasMany
    {
        return $this->hasMany(OcorrenciaDia::class, 'ocorrencia_id')->orderBy('data');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaDocumental::class, 'ocorrencia_frequencia_id')->latest();
    }
}
