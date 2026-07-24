<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VantagemFuncional extends Model
{
    use HasFactory;

    protected $table = 'vantagens_funcionais';

    protected $fillable = [
        'servidor_id',
        'evento_id',
        'percentual',
        'valor',
        'quantidade',
        'nivel',
        'texto',
        'data_inicio',
        'data_fim',
        'referencia_documento',
        'observacao',
        'registrado_por_id',
    ];

    protected $casts = [
        'percentual' => 'decimal:2',
        'valor' => 'decimal:2',
        'quantidade' => 'decimal:2',
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function scopeVigenteEm(Builder $query, mixed $data): Builder
    {
        return $query->where('data_inicio', '<=', $data)
            ->where(fn (Builder $fim) => $fim->whereNull('data_fim')->orWhere('data_fim', '>=', $data));
    }
}
