<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'uuid',
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
        'hash_anterior',
        'created_at',
        'hash_registro',
        'updated_at',
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

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Registros de auditoria sao imutaveis.');
        });
        static::deleting(function (): never {
            throw new LogicException('Registros de auditoria nao podem ser excluidos.');
        });
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
