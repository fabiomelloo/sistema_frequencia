<?php

namespace App\Models;

use App\Enums\VinculoServidor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinculoFuncional extends Model
{
    use HasFactory;

    protected $table = 'vinculos_funcionais';

    protected $fillable = [
        'servidor_id',
        'matricula',
        'tipo_vinculo',
        'cargo',
        'carga_horaria',
        'data_inicio',
        'data_fim',
        'ato_referencia',
        'observacao',
        'registrado_por_id',
    ];

    protected $casts = [
        'tipo_vinculo' => VinculoServidor::class,
        'carga_horaria' => 'integer',
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function scopeVigenteEm(Builder $query, mixed $data): Builder
    {
        return $query
            ->where(fn (Builder $inicio) => $inicio->whereNull('data_inicio')->orWhere('data_inicio', '<=', $data))
            ->where(fn (Builder $fim) => $fim->whereNull('data_fim')->orWhere('data_fim', '>=', $data));
    }
}
