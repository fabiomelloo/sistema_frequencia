<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'setor_id',
        'role',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'ativo' => 'boolean',
        'tentativas_login_falhas' => 'integer',
        'bloqueado_ate' => 'datetime',
        'ultimo_login_em' => 'datetime',
        'desativado_em' => 'datetime',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    public function validacoes(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'id_validador');
    }

    public function isCentral(): bool
    {
        return $this->role === UserRole::CENTRAL;
    }

    public function isSetorial(): bool
    {
        return $this->role === UserRole::SETORIAL;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isGestor(): bool
    {
        return $this->role === UserRole::GESTOR;
    }

    public function isAuditor(): bool
    {
        return $this->role === UserRole::AUDITOR;
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_ate?->isFuture() ?? false;
    }
}
