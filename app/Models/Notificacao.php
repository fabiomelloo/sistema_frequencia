<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = [
        'user_id',
        'tipo',
        'titulo',
        'mensagem',
        'link',
        'lida_em',
    ];

    protected $casts = [
        'lida_em' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isLida(): bool
    {
        return $this->lida_em !== null;
    }

    public function marcarComoLida(): void
    {
        $this->update(['lida_em' => now()]);
    }

    public function scopeNaoLidas($query)
    {
        return $query->whereNull('lida_em');
    }

    public function scopeDoUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
