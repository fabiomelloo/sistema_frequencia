<?php

namespace App\Models;

use App\Enums\OrigemInformacaoItem;
use App\Enums\UnidadeLancamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FolhaFrequenciaItem extends Model
{
    use HasFactory;

    protected $table = 'folha_frequencia_itens';

    protected $fillable = [
        'folha_frequencia_servidor_id',
        'evento_id',
        'vantagem_funcional_id',
        'codigo_evento',
        'sigla',
        'descricao',
        'unidade_lancamento',
        'origem_informacao',
        'percentual',
        'valor',
        'quantidade',
        'nivel',
        'texto',
        'observacao',
        'referencia_documento',
        'exige_documento',
        'vigencia_inicio',
        'vigencia_fim',
        'editavel_pelo_setor',
        'atualizado_por_id',
    ];

    protected $casts = [
        'unidade_lancamento' => UnidadeLancamento::class,
        'origem_informacao' => OrigemInformacaoItem::class,
        'percentual' => 'decimal:2',
        'valor' => 'decimal:2',
        'quantidade' => 'decimal:2',
        'vigencia_inicio' => 'date',
        'vigencia_fim' => 'date',
        'editavel_pelo_setor' => 'boolean',
        'exige_documento' => 'boolean',
    ];

    public function servidorFolha(): BelongsTo
    {
        return $this->belongsTo(FolhaFrequenciaServidor::class, 'folha_frequencia_servidor_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoFolha::class, 'evento_id');
    }

    public function vantagemFuncional(): BelongsTo
    {
        return $this->belongsTo(VantagemFuncional::class);
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaDocumental::class, 'folha_frequencia_item_id')->latest();
    }

    public function conteudo(): string
    {
        return match ($this->unidade_lancamento) {
            UnidadeLancamento::PERCENTUAL => $this->percentual !== null ? $this->formatarNumero($this->percentual).'%' : 'Não informado',
            UnidadeLancamento::VALOR => $this->valor !== null ? 'R$ '.number_format((float) $this->valor, 2, ',', '.') : 'Não informado',
            UnidadeLancamento::DIAS => $this->quantidade !== null ? $this->formatarNumero($this->quantidade).' dia(s)' : 'Não informado',
            UnidadeLancamento::HORAS => $this->quantidade !== null ? $this->formatarNumero($this->quantidade).' hora(s)' : 'Não informado',
            UnidadeLancamento::NIVEL => $this->nivel ?? 'Não informado',
            UnidadeLancamento::TEXTO => $this->texto ?? 'Não informado',
            UnidadeLancamento::MARCADOR => 'Sim',
        };
    }

    private function formatarNumero(mixed $numero): string
    {
        $formatado = number_format((float) $numero, 2, ',', '.');

        return rtrim(rtrim($formatado, '0'), ',');
    }
}
