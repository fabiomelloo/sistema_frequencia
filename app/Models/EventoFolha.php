<?php

namespace App\Models;

use App\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventoFolha extends Model
{
    protected $table = 'eventos_folha';
    protected $fillable = [
        'codigo_evento',
        'descricao',
        'tipo_evento',
        'exige_dias',
        'exige_valor',
        'valor_minimo',
        'valor_maximo',
        'dias_maximo',
        'exige_observacao',
        'exige_porcentagem',
        'ativo'
    ];

    protected $casts = [
        'tipo_evento' => TipoEvento::class,
    ];

    public function setoresComDireito(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'evento_setor', 'evento_id', 'setor_id')
            ->where('evento_setor.ativo', true)
            ->withPivot('ativo');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'evento_id');
    }

    public function temDireitoNoSetor($setorId): bool
    {
        return $this->setoresComDireito()
            ->where('setor_id', $setorId)
            ->exists();
    }
}
