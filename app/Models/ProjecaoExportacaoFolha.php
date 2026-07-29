<?php

namespace App\Models;

use App\Enums\ProjecaoExportacaoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjecaoExportacaoFolha extends Model
{
    protected $table = 'projecoes_exportacao_folha';

    protected $fillable = [
        'folha_frequencia_item_id',
        'folha_frequencia_id',
        'competencia_id',
        'rodada_conferencia',
        'servidor_id',
        'codigo_evento',
        'matricula',
        'valor',
        'status',
        'exportacao_id',
        'exportado_em',
    ];

    protected $casts = [
        'rodada_conferencia' => 'integer',
        'valor' => 'decimal:2',
        'status' => ProjecaoExportacaoStatus::class,
        'exportado_em' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequenciaItem::class, 'folha_frequencia_item_id');
    }

    public function folha(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequencia::class, 'folha_frequencia_id');
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function exportacao(): BelongsTo
    {
        return $this->belongsTo(ExportacaoFolha::class, 'exportacao_id');
    }
}
