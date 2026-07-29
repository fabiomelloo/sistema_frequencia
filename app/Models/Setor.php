<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setor extends Model
{
    use HasFactory;

    protected $table = 'setores';

    protected $fillable = ['nome', 'sigla', 'codigo_externo', 'setor_pai_id', 'ativo'];

    public function setorPai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'setor_pai_id');
    }

    public function setoresFilhos(): HasMany
    {
        return $this->hasMany(self::class, 'setor_pai_id');
    }

    public function possuiAncestral(int $setorId): bool
    {
        $atual = $this->setorPai;
        $visitados = [];

        while ($atual) {
            if ($atual->id === $setorId) {
                return true;
            }

            if (isset($visitados[$atual->id])) {
                return false;
            }

            $visitados[$atual->id] = true;
            $atual = $atual->setorPai;
        }

        return false;
    }

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

    public function folhasFrequencia(): HasMany
    {
        return $this->hasMany(FolhaFrequencia::class);
    }
}
