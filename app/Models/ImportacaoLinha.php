<?php

namespace App\Models;

use App\Enums\ImportacaoLinhaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacaoLinha extends Model
{
    protected $table = 'importacao_linhas';

    protected $fillable = [
        'importacao_id',
        'numero_linha',
        'conteudo_original',
        'dados',
        'status',
        'erros',
        'lancamento_id',
        'ocorrencia_id',
    ];

    protected $casts = [
        'dados' => 'array',
        'erros' => 'array',
        'status' => ImportacaoLinhaStatus::class,
    ];

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(Importacao::class);
    }

    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(LancamentoSetorial::class);
    }

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(OcorrenciaFrequencia::class, 'ocorrencia_id');
    }
}
