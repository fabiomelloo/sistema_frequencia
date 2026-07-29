<?php

namespace App\Services;

use App\Enums\OrigemInformacaoItem;
use App\Models\DesignacaoFuncional;
use App\Models\EventoFolha;
use App\Models\LotacaoHistorico;
use App\Models\Servidor;
use App\Models\VantagemFuncional;
use App\Models\VinculoFuncional;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HistoricoFuncionalService
{
    public function cadastrarServidor(array $dados, ?int $usuarioId): Servidor
    {
        return DB::transaction(function () use ($dados, $usuarioId): Servidor {
            $dadosServidor = [
                'matricula' => $dados['matricula'],
                'cpf' => $dados['cpf'] ?? null,
                'nome' => $dados['nome'],
                'cargo' => $dados['cargo'],
                'vinculo' => $dados['tipo_vinculo'],
                'carga_horaria' => $dados['carga_horaria'],
                'setor_id' => $dados['setor_id'],
                'data_admissao' => $dados['data_admissao'],
                'origem_registro' => 'MANUAL',
                'ativo' => true,
            ];

            $servidor = Servidor::create($dadosServidor);
            $servidor->vinculosFuncionais()->create([
                'matricula' => $dados['matricula'],
                'tipo_vinculo' => $dados['tipo_vinculo'],
                'cargo' => $dados['cargo'],
                'carga_horaria' => $dados['carga_horaria'],
                'data_inicio' => $dados['data_admissao'],
                'ato_referencia' => $dados['ato_referencia'] ?? null,
                'registrado_por_id' => $usuarioId,
            ]);
            LotacaoHistorico::create([
                'servidor_id' => $servidor->id,
                'setor_id' => $dados['setor_id'],
                'data_inicio' => $dados['data_admissao'],
                'observacao' => 'Lotação inicial registrada no cadastro do servidor.',
            ]);

            AuditService::criou('Servidor', $servidor->id, 'Servidor cadastrado com vínculo e lotação iniciais.');

            return $servidor;
        });
    }

    public function registrarVinculo(Servidor $servidor, array $dados, ?int $usuarioId): VinculoFuncional
    {
        return DB::transaction(function () use ($servidor, $dados, $usuarioId): VinculoFuncional {
            $inicio = Carbon::parse($dados['data_inicio'])->startOfDay();
            $atual = $servidor->vinculosFuncionais()->whereNull('data_fim')->lockForUpdate()->latest('data_inicio')->first();

            if ($atual?->data_inicio && $inicio->lte($atual->data_inicio)) {
                throw new InvalidArgumentException('A nova vigência deve começar depois do vínculo funcional atual.');
            }

            if ($atual) {
                $atual->update(['data_fim' => $inicio->copy()->subDay()]);
            }

            $vinculo = $servidor->vinculosFuncionais()->create($dados + [
                'matricula' => $servidor->matricula,
                'registrado_por_id' => $usuarioId,
            ]);

            $servidor->update([
                'vinculo' => $dados['tipo_vinculo'],
                'cargo' => $dados['cargo'],
                'carga_horaria' => $dados['carga_horaria'],
            ]);

            AuditService::registrar('REGISTROU_VINCULO_FUNCIONAL', 'Servidor', $servidor->id, "Nova vigência funcional iniciada em {$inicio->format('d/m/Y')}.");

            return $vinculo;
        });
    }

    public function registrarDesignacao(Servidor $servidor, array $dados, ?int $usuarioId): DesignacaoFuncional
    {
        return DB::transaction(function () use ($servidor, $dados, $usuarioId): DesignacaoFuncional {
            $this->impedirSobreposicao($servidor->designacoesFuncionais()->where('tipo', $dados['tipo']), $dados);

            $designacao = $servidor->designacoesFuncionais()->create($dados + ['registrado_por_id' => $usuarioId]);
            AuditService::registrar('REGISTROU_DESIGNACAO_FUNCIONAL', 'Servidor', $servidor->id, "Designação {$dados['tipo']} registrada com vigência própria.");

            return $designacao;
        });
    }

    public function registrarVantagem(Servidor $servidor, EventoFolha $evento, array $dados, ?int $usuarioId): VantagemFuncional
    {
        if (! $evento->ativo || ! $evento->regra_validada || $evento->origem_informacao !== OrigemInformacaoItem::CADASTRO_FUNCIONAL) {
            throw new InvalidArgumentException('O item não está habilitado para o cadastro funcional.');
        }

        return DB::transaction(function () use ($servidor, $evento, $dados, $usuarioId): VantagemFuncional {
            $this->impedirSobreposicao($servidor->vantagensFuncionais()->where('evento_id', $evento->id), $dados);

            $vantagem = $servidor->vantagensFuncionais()->create($dados + [
                'evento_id' => $evento->id,
                'registrado_por_id' => $usuarioId,
            ]);
            AuditService::registrar('REGISTROU_VANTAGEM_FUNCIONAL', 'Servidor', $servidor->id, "Vantagem {$evento->codigo_evento} registrada com vigência própria.");

            return $vantagem;
        });
    }

    public function encerrar(Model $registro, Carbon $dataFim, Servidor $servidor): void
    {
        if ($registro->servidor_id !== $servidor->id) {
            throw new InvalidArgumentException('O registro não pertence ao servidor informado.');
        }
        if ($dataFim->lt($registro->data_inicio)) {
            throw new InvalidArgumentException('O fim da vigência não pode ser anterior ao início.');
        }
        if ($registro->data_fim && $dataFim->gt($registro->data_fim)) {
            throw new InvalidArgumentException('A vigência já foi encerrada em data anterior.');
        }

        $registro->update(['data_fim' => $dataFim]);
        AuditService::registrar('ENCERROU_VIGENCIA_FUNCIONAL', 'Servidor', $servidor->id, "Vigência encerrada em {$dataFim->format('d/m/Y')}.");
    }

    private function impedirSobreposicao(mixed $query, array $dados): void
    {
        $inicio = Carbon::parse($dados['data_inicio'])->toDateString();
        $fim = isset($dados['data_fim']) && $dados['data_fim'] ? Carbon::parse($dados['data_fim'])->toDateString() : null;

        $sobrepoe = $query
            ->where(fn ($existente) => $existente->whereNull('data_fim')->orWhere('data_fim', '>=', $inicio))
            ->when($fim, fn ($existente) => $existente->where('data_inicio', '<=', $fim))
            ->exists();

        if ($sobrepoe) {
            throw new InvalidArgumentException('Já existe um registro do mesmo tipo com vigência sobreposta.');
        }
    }
}
