<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Servidor extends Model
{
    protected $table = 'servidores';
    protected $fillable = [
        'matricula', 
        'nome', 
        'setor_id', 
        'origem_registro', 
        'ativo',
        'funcao_vigia',
        'trabalha_noturno',
        'data_admissao',
        'data_desligamento',
    ];

    protected $casts = [
        'data_admissao' => 'date',
        'data_desligamento' => 'date',
        'ativo' => 'boolean',
        'funcao_vigia' => 'boolean',
        'trabalha_noturno' => 'boolean',
    ];

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoSetorial::class, 'servidor_id');
    }

    public function lotacoes(): HasMany
    {
        return $this->hasMany(LotacaoHistorico::class, 'servidor_id');
    }

    public function lancamentosAtivos()
    {
        return $this->lancamentos()
            ->whereNotIn('status', [
                \App\Enums\LancamentoStatus::EXPORTADO->value, 
                \App\Enums\LancamentoStatus::REJEITADO->value,
                \App\Enums\LancamentoStatus::ESTORNADO->value,
            ])
            ->orderBy('updated_at', 'desc');
    }

    /**
     * Verifica se o servidor está ativo numa competência (YYYY-MM).
     */
    public function estaAtivoNaCompetencia(string $competencia): bool
    {
        if (!$this->ativo && !$this->data_desligamento) {
            return false;
        }

        if ($this->data_desligamento) {
            $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();
            $inicioMes = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
            
            // Se foi desligado antes do início do mês, não pode
            if ($this->data_desligamento->lt($inicioMes)) {
                return false;
            }
        }

        if ($this->data_admissao) {
            $fimMes = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth();
            
            // Se foi admitido depois do fim do mês, não pode
            if ($this->data_admissao->gt($fimMes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retorna o setor do servidor na competência (por histórico de lotação).
     * Se não houver histórico, retorna o setor atual.
     */
    public function setorNaCompetencia(string $competencia): int
    {
        $setorHistorico = LotacaoHistorico::setorNaCompetencia($this->id, $competencia);
        return $setorHistorico ?? $this->setor_id;
    }
}
