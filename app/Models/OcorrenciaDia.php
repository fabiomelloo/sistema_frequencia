<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcorrenciaDia extends Model
{
    use HasFactory;

    protected $table = 'ocorrencia_dias';

    protected $fillable = ['data'];

    protected $casts = ['data' => 'date'];

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(OcorrenciaFrequencia::class, 'ocorrencia_id');
    }
}
