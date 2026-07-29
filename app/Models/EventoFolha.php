<?php

namespace App\Models;

use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class EventoFolha extends Model
{
    use HasFactory;

    protected $table = 'eventos_folha';

    protected $fillable = [
        'codigo_evento',
        'sigla',
        'descricao',
        'tipo_evento',
        'unidade_lancamento',
        'origem_informacao',
        'gera_efeito_financeiro',
        'exige_dias',
        'exige_valor',
        'valor_minimo',
        'valor_maximo',
        'dias_maximo',
        'exige_observacao',
        'exige_documento',
        'instrucoes_lancamento',
        'regra_validada',
        'regra_validada_por_id',
        'regra_validada_em',
        'exige_porcentagem',
        'ativo',
    ];

    protected $casts = [
        'tipo_evento' => TipoEvento::class,
        'unidade_lancamento' => UnidadeLancamento::class,
        'origem_informacao' => OrigemInformacaoItem::class,
        'gera_efeito_financeiro' => 'boolean',
        'exige_dias' => 'boolean',
        'exige_valor' => 'boolean',
        'exige_observacao' => 'boolean',
        'exige_documento' => 'boolean',
        'regra_validada' => 'boolean',
        'regra_validada_em' => 'datetime',
        'exige_porcentagem' => 'boolean',
        'ativo' => 'boolean',
        'valor_minimo' => 'decimal:2',
        'valor_maximo' => 'decimal:2',
        'dias_maximo' => 'integer',
    ];

    public function setoresComDireito(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'evento_setor', 'evento_id', 'setor_id')
            ->where('evento_setor.ativo', true)
            ->withPivot('ativo');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'evento_id');
    }

    public function vantagensFuncionais(): HasMany
    {
        return $this->hasMany(VantagemFuncional::class, 'evento_id');
    }

    public function itensFrequencia(): HasMany
    {
        return $this->hasMany(FolhaFrequenciaItem::class, 'evento_id');
    }

    public function regraValidadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regra_validada_por_id');
    }

    public function configuracaoPendente(): bool
    {
        return ! $this->regra_validada;
    }

    public function temDireitoNoSetor($setorId): bool
    {
        return Cache::remember("evento_{$this->id}_setor_{$setorId}_direito", now()->addHours(1), function () use ($setorId) {
            return $this->setoresComDireito()
                ->where('setor_id', $setorId)
                ->exists();
        });
    }
}
