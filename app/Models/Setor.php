<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setor extends Model
{
    protected $table = 'setores';
    protected $fillable = ['nome', 'sigla', 'ativo'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'setor_id');
    }

    public function servidores(): HasMany
    {
        return $this->hasMany(Servidor::class, 'setor_id');
    }

    public function eventosPermitidos()
    {
        return $this->belongsToMany(EventoFolha::class, 'evento_setor', 'setor_id', 'evento_id')
            ->where('evento_setor.ativo', true)
            ->withPivot('ativo');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'setor_origem_id');
    }
}
