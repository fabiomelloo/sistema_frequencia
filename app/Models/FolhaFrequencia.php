<?php

namespace App\Models;

use App\Enums\FolhaFrequenciaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FolhaFrequencia extends Model
{
    use HasFactory;

    protected $table = 'folhas_frequencia';

    protected $fillable = [
        'setor_id', 'competencia_id', 'status', 'rodada_conferencia', 'criado_por_id',
        'finalizado_por_id', 'finalizada_em',
        'conferido_por_id', 'conferida_em', 'motivo_devolucao',
    ];

    protected $casts = [
        'status' => FolhaFrequenciaStatus::class,
        'rodada_conferencia' => 'integer',
        'finalizada_em' => 'datetime',
        'conferida_em' => 'datetime',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function servidores(): HasMany
    {
        return $this->hasMany(FolhaFrequenciaServidor::class)->orderBy('nome');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function finalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizado_por_id');
    }

    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por_id');
    }

    public function estaFinalizada(): bool
    {
        return $this->status === FolhaFrequenciaStatus::FINALIZADA;
    }

    public function editavelPeloSetor(): bool
    {
        return $this->status->editavelPeloSetor();
    }

    public function estaAprovada(): bool
    {
        return $this->status === FolhaFrequenciaStatus::APROVADA;
    }

    public function estaDevolvida(): bool
    {
        return $this->status === FolhaFrequenciaStatus::DEVOLVIDA;
    }
}
