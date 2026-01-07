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
        'dias_trabalhados',
        'dias_noturnos',
        'valor',
        'valor_gratificacao',
        'porcentagem_insalubridade',
        'porcentagem_periculosidade',
        'adicional_turno',
        'adicional_noturno',
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
        'dias_trabalhados' => 'integer',
        'dias_noturnos' => 'integer',
        'valor' => 'decimal:2',
        'valor_gratificacao' => 'decimal:2',
        'porcentagem_insalubridade' => 'integer',
        'porcentagem_periculosidade' => 'integer',
        'adicional_turno' => 'decimal:2',
        'adicional_noturno' => 'decimal:2',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
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
