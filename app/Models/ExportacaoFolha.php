<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExportacaoFolha extends Model
{
    protected $table = 'exportacoes_folha';

    protected $fillable = [
        'periodo',
        'nome_arquivo',
        'hash_arquivo',
        'usuario_id',
        'quantidade_lancamentos',
        'data_exportacao',
    ];

    protected $casts = [
        'data_exportacao' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'quantidade_lancamentos' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function lancamentos(): BelongsToMany
    {
        return $this->belongsToMany(
            LancamentoSetorial::class,
            'exportacao_lancamento',
            'exportacao_id',
            'lancamento_id'
        )->withTimestamps();
    }
}
