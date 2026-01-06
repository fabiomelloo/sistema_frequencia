<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LancamentoSetorial extends Model
{
    protected $table = 'lancamentos_setoriais';
    protected $fillable = [
        'servidor_id',
        'evento_id',
        'setor_origem_id',
        'dias_lancados',
        'valor',
        'porcentagem_insalubridade',
        'observacao',
        'status',
        'motivo_rejeicao',
        'id_validador',
        'validated_at',
        'exportado_em'
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'exportado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
        return $this->belongsTo(EventoFolha::class, 'evento_id')->with('setoresComDireito');
        
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
    }
    }

    public function setorOrigem(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_validador');
    }

    public function isPendente(): bool
    {
        return $this->status === 'PENDENTE';
    }

    public function isConferido(): bool
    {
        return $this->status === 'CONFERIDO';
    }

    public function isRejeitado(): bool
    {
        return $this->status === 'REJEITADO';
    }

    public function isExportado(): bool
    {
        return $this->status === 'EXPORTADO';
    }

    public function podeSerEditado(): bool
    {
        return in_array($this->status, ['PENDENTE']);
    }
}
