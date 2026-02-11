<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'acao',
        'modelo',
        'modelo_id',
        'descricao',
        'dados_antes',
        'dados_depois',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'dados_antes' => 'array',
        'dados_depois' => 'array',
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes para filtragem
    public function scopeByAcao($query, string $acao)
    {
        return $query->where('acao', $acao);
    }

    public function scopeByModelo($query, string $modelo)
    {
        return $query->where('modelo', $modelo);
    }

    public function scopeByUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentes($query, int $dias = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }
}
