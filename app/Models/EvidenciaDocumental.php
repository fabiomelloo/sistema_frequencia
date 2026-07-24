<?php

namespace App\Models;

use App\Enums\EvidenciaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenciaDocumental extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evidencias_documentais';

    protected $fillable = [
        'ocorrencia_frequencia_id', 'folha_frequencia_item_id', 'nome_original',
        'caminho_arquivo', 'mime_type', 'tamanho_bytes', 'hash_sha256', 'status',
        'descricao', 'motivo_revisao', 'enviado_por_id', 'revisado_por_id', 'revisado_em',
    ];

    protected $casts = [
        'status' => EvidenciaStatus::class,
        'tamanho_bytes' => 'integer',
        'revisado_em' => 'datetime',
    ];

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(OcorrenciaFrequencia::class, 'ocorrencia_frequencia_id');
    }

    public function itemFrequencia(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequenciaItem::class, 'folha_frequencia_item_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function tamanhoFormatado(): string
    {
        if ($this->tamanho_bytes < 1024) {
            return $this->tamanho_bytes.' B';
        }
        if ($this->tamanho_bytes < 1024 * 1024) {
            return number_format($this->tamanho_bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($this->tamanho_bytes / (1024 * 1024), 1, ',', '.').' MB';
    }
}
