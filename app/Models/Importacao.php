<?php

namespace App\Models;

use App\Enums\ImportacaoFinalidade;
use App\Enums\ImportacaoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Importacao extends Model
{
    protected $table = 'importacoes';

    protected $fillable = [
        'setor_id',
        'usuario_id',
        'nome_original',
        'caminho_arquivo',
        'hash_arquivo',
        'tipo_arquivo',
        'finalidade',
        'status',
        'total_linhas',
        'linhas_validas',
        'linhas_invalidas',
        'processada_em',
    ];

    protected $casts = [
        'finalidade' => ImportacaoFinalidade::class,
        'status' => ImportacaoStatus::class,
        'total_linhas' => 'integer',
        'linhas_validas' => 'integer',
        'linhas_invalidas' => 'integer',
        'processada_em' => 'datetime',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linhas(): HasMany
    {
        return $this->hasMany(ImportacaoLinha::class)->orderBy('numero_linha');
    }

    public function podeProcessar(): bool
    {
        return $this->status === ImportacaoStatus::PENDENTE
            && $this->linhas_invalidas === 0
            && $this->linhas_validas > 0;
    }
}
