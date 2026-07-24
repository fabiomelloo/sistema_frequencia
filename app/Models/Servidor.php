<?php

namespace App\Models;

use App\Enums\LancamentoStatus;
use App\Enums\VinculoServidor;
use App\Observers\ServidorObserver;
use App\Traits\MaskedCpf;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ServidorObserver::class)]
class Servidor extends Model
{
    use HasFactory, MaskedCpf;

    protected $table = 'servidores';

    protected $fillable = [
        'matricula',
        'cpf',
        'nome',
        'cargo',
        'vinculo',
        'carga_horaria',
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
        'vinculo' => VinculoServidor::class,
        'carga_horaria' => 'integer',
    ];

    /**
     * Mutator para limpar CPF antes de salvar (remove formatação).
     */
    public function setCpfAttribute($value): void
    {
        if ($value) {
            // Remove caracteres não numéricos
            $this->attributes['cpf'] = preg_replace('/\D/', '', $value);
        } else {
            $this->attributes['cpf'] = null;
        }
    }

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

    public function vinculosFuncionais(): HasMany
    {
        return $this->hasMany(VinculoFuncional::class, 'servidor_id')->orderByDesc('data_inicio');
    }

    public function designacoesFuncionais(): HasMany
    {
        return $this->hasMany(DesignacaoFuncional::class, 'servidor_id')->orderByDesc('data_inicio');
    }

    public function vantagensFuncionais(): HasMany
    {
        return $this->hasMany(VantagemFuncional::class, 'servidor_id')->orderByDesc('data_inicio');
    }

    public function vinculoFuncionalEm(mixed $data): ?VinculoFuncional
    {
        return $this->vinculosFuncionais()->vigenteEm($data)->first();
    }

    public function ocorrenciasFrequencia(): HasMany
    {
        return $this->hasMany(OcorrenciaFrequencia::class, 'servidor_id');
    }

    public function folhasFrequencia(): HasMany
    {
        return $this->hasMany(FolhaFrequenciaServidor::class, 'servidor_id');
    }

    public function lancamentosAtivos()
    {
        return $this->lancamentos()
            ->whereNotIn('status', [
                LancamentoStatus::EXPORTADO->value,
                LancamentoStatus::REJEITADO->value,
                LancamentoStatus::ESTORNADO->value,
                LancamentoStatus::CANCELADO->value,
            ])
            ->orderBy('updated_at', 'desc');
    }

    /**
     * Verifica se o servidor está ativo numa competência (YYYY-MM).
     */
    public function estaAtivoNaCompetencia(string $competencia): bool
    {
        if (! $this->ativo && ! $this->data_desligamento) {
            return false;
        }

        [$inicioPeriodo, $fimPeriodo] = Competencia::periodoDaReferencia($competencia);

        if ($this->data_desligamento) {
            // Se foi desligado antes do início do período, não pode
            if ($this->data_desligamento->lt($inicioPeriodo)) {
                return false;
            }
        }

        if ($this->data_admissao) {
            // Se foi admitido depois do fim do período, não pode
            if ($this->data_admissao->gt($fimPeriodo)) {
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
