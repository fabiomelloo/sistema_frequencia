<?php

namespace App\Services;

use App\Enums\EvidenciaStatus;
use App\Models\EvidenciaDocumental;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class EvidenciaDocumentalService
{
    public function armazenar(Model $alvo, UploadedFile $arquivo, ?string $descricao, User $usuario): EvidenciaDocumental
    {
        if (! $alvo instanceof OcorrenciaFrequencia && ! $alvo instanceof FolhaFrequenciaItem) {
            throw new InvalidArgumentException('O documento precisa estar vinculado a uma ocorrência ou item da frequência.');
        }

        $caminho = $arquivo->store('evidencias/'.now()->format('Y/m'), 'local');
        if (! $caminho) {
            throw new RuntimeException('Não foi possível armazenar o documento. Tente novamente.');
        }

        try {
            $caminhoAbsoluto = Storage::disk('local')->path($caminho);
            $hash = hash_file('sha256', $caminhoAbsoluto);
            if (! is_string($hash)) {
                throw new RuntimeException('Não foi possível verificar a integridade do documento enviado.');
            }
            $atributoAlvo = $alvo instanceof OcorrenciaFrequencia
                ? 'ocorrencia_frequencia_id'
                : 'folha_frequencia_item_id';

            return DB::transaction(function () use ($alvo, $arquivo, $descricao, $usuario, $caminho, $hash, $atributoAlvo): EvidenciaDocumental {
                $this->garantirAlvoEditavel($alvo, $usuario);
                $duplicada = EvidenciaDocumental::query()
                    ->where($atributoAlvo, $alvo->id)
                    ->where('hash_sha256', $hash)
                    ->exists();
                if ($duplicada) {
                    throw new InvalidArgumentException('Este mesmo arquivo já está anexado ao registro.');
                }

                return EvidenciaDocumental::create([
                    $atributoAlvo => $alvo->id,
                    'nome_original' => $this->nomeOriginalSeguro($arquivo),
                    'caminho_arquivo' => $caminho,
                    'mime_type' => (string) $arquivo->getMimeType(),
                    'tamanho_bytes' => (int) $arquivo->getSize(),
                    'hash_sha256' => $hash,
                    'status' => EvidenciaStatus::PENDENTE,
                    'descricao' => $descricao ?: null,
                    'enviado_por_id' => $usuario->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($caminho);
            throw $exception;
        }
    }

    public function revisar(
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $servidorFolha,
        EvidenciaDocumental $evidencia,
        EvidenciaStatus $status,
        ?string $motivo,
        User $usuario,
    ): EvidenciaDocumental {
        return DB::transaction(function () use ($folha, $servidorFolha, $evidencia, $status, $motivo, $usuario): EvidenciaDocumental {
            $folha = FolhaFrequencia::query()->lockForUpdate()->findOrFail($folha->id);
            $evidencia = EvidenciaDocumental::query()->lockForUpdate()->findOrFail($evidencia->id);
            if (! $folha->estaFinalizada()) {
                throw new InvalidArgumentException('Documentos só podem ser revisados enquanto a folha aguarda conferência.');
            }
            if ($servidorFolha->folha_frequencia_id !== $folha->id || ! $this->pertenceAoServidor($evidencia, $servidorFolha, $folha)) {
                throw new InvalidArgumentException('O documento não pertence ao servidor desta frequência.');
            }
            if ($status === EvidenciaStatus::RECUSADA && mb_strlen(trim((string) $motivo)) < 10) {
                throw new InvalidArgumentException('Explique objetivamente por que o documento foi recusado.');
            }

            $evidencia->update([
                'status' => $status,
                'motivo_revisao' => $status === EvidenciaStatus::RECUSADA ? $motivo : null,
                'revisado_por_id' => $usuario->id,
                'revisado_em' => now(),
            ]);

            $servidorFolha->conferencias()->where('rodada', $folha->rodada_conferencia)->delete();

            return $evidencia->refresh();
        });
    }

    public function remover(EvidenciaDocumental $evidencia, User $usuario): void
    {
        DB::transaction(function () use ($evidencia, $usuario): void {
            $evidencia = EvidenciaDocumental::query()->lockForUpdate()->findOrFail($evidencia->id);
            $alvo = $evidencia->folha_frequencia_item_id ? $evidencia->itemFrequencia : $evidencia->ocorrencia;
            $this->garantirAlvoEditavel($alvo, $usuario);
            if (Storage::disk('local')->exists($evidencia->caminho_arquivo)
                && ! Storage::disk('local')->delete($evidencia->caminho_arquivo)) {
                throw new RuntimeException('Não foi possível remover o arquivo armazenado.');
            }
            $evidencia->delete();
        });
    }

    public function caminhoIntegro(EvidenciaDocumental $evidencia): string
    {
        $disco = Storage::disk('local');
        if (! $disco->exists($evidencia->caminho_arquivo)) {
            throw new InvalidArgumentException('O documento não está disponível no armazenamento privado.');
        }

        $caminho = $disco->path($evidencia->caminho_arquivo);
        if (! hash_equals($evidencia->hash_sha256, hash_file('sha256', $caminho))) {
            throw new InvalidArgumentException('O documento falhou na verificação de integridade.');
        }

        return $caminho;
    }

    private function pertenceAoServidor(
        EvidenciaDocumental $evidencia,
        FolhaFrequenciaServidor $servidorFolha,
        FolhaFrequencia $folha,
    ): bool {
        if ($evidencia->folha_frequencia_item_id) {
            return $evidencia->itemFrequencia->folha_frequencia_servidor_id === $servidorFolha->id;
        }

        return $evidencia->ocorrencia->servidor_id === $servidorFolha->servidor_id
            && $evidencia->ocorrencia->setor_id === $folha->setor_id
            && $evidencia->ocorrencia->competencia_id === $folha->competencia_id;
    }

    private function garantirAlvoEditavel(Model $alvo, User $usuario): void
    {
        if (! $usuario->role->podeFazerLancamentos()) {
            throw new InvalidArgumentException('Seu perfil não pode alterar documentos comprobatórios.');
        }

        if ($alvo instanceof FolhaFrequenciaItem) {
            $referencia = FolhaFrequenciaItem::query()->with('servidorFolha')->findOrFail($alvo->id);
            $folha = FolhaFrequencia::query()->with('competencia')->lockForUpdate()->findOrFail($referencia->servidorFolha->folha_frequencia_id);
            FolhaFrequenciaItem::query()->lockForUpdate()->findOrFail($alvo->id);
            if ($folha->setor_id !== $usuario->setor_id || ! $folha->editavelPeloSetor() || ! $folha->competencia->estaAberta()) {
                throw new InvalidArgumentException('A frequência não está aberta para alteração de documentos.');
            }

            return;
        }

        $referencia = OcorrenciaFrequencia::query()->findOrFail($alvo->id);
        $folha = FolhaFrequencia::query()
            ->where('setor_id', $referencia->setor_id)
            ->where('competencia_id', $referencia->competencia_id)
            ->lockForUpdate()
            ->first();
        $ocorrencia = OcorrenciaFrequencia::query()->with('competencia')->lockForUpdate()->findOrFail($alvo->id);
        if ($ocorrencia->setor_id !== $usuario->setor_id || ! $ocorrencia->competencia->estaAberta()) {
            throw new InvalidArgumentException('A ocorrência não está aberta para alteração de documentos.');
        }

        if ($folha && ! $folha->editavelPeloSetor()) {
            throw new InvalidArgumentException('A frequência já foi enviada e seus documentos estão bloqueados.');
        }
    }

    private function nomeOriginalSeguro(UploadedFile $arquivo): string
    {
        $nome = basename(str_replace('\\', '/', $arquivo->getClientOriginalName()));
        $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?: 'documento';

        return Str::limit($nome, 255, '');
    }
}
