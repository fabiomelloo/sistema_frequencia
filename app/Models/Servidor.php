<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servidor extends Model
{
    protected $table = 'servidores';
    protected $fillable = ['matricula', 'nome', 'setor_id', 'origem_registro', 'ativo'];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'servidor_id');
    }

    public function lancamentosAtivos()
    {
        return $this->lancamentos()
            ->whereNotIn('status', ['EXPORTADO', 'REJEITADO'])
            ->orderBy('updated_at', 'desc');
    }
}
