<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditIntegrityService
{
    /** @return array{ok: bool, total: int, registro_invalido: ?int, motivo: ?string, ultimo_hash: ?string} */
    public function verificar(): array
    {
        $hashAnterior = null;
        $total = 0;

        foreach (DB::table('audit_logs')->orderBy('id')->cursor() as $registro) {
            $total++;
            if (! hash_equals((string) ($registro->hash_anterior ?? ''), (string) ($hashAnterior ?? ''))) {
                return $this->falha($total, $registro->id, 'A ligação com o registro anterior é inválida.', $hashAnterior);
            }

            $hashCalculado = $this->calcularHash($registro);
            if (! hash_equals((string) $registro->hash_registro, $hashCalculado)) {
                return $this->falha($total, $registro->id, 'O conteúdo do registro foi alterado.', $hashAnterior);
            }

            $hashAnterior = $registro->hash_registro;
        }

        $estado = DB::table('audit_chain_state')->where('id', 1)->value('ultimo_hash');
        if (! hash_equals((string) ($estado ?? ''), (string) ($hashAnterior ?? ''))) {
            return $this->falha($total, null, 'O estado final da cadeia não corresponde ao último registro.', $hashAnterior);
        }

        return [
            'ok' => true,
            'total' => $total,
            'registro_invalido' => null,
            'motivo' => null,
            'ultimo_hash' => $hashAnterior,
        ];
    }

    public function calcularHash(object $registro): string
    {
        $campos = [
            'id', 'uuid', 'user_id', 'user_name', 'acao', 'modelo', 'modelo_id', 'descricao',
            'dados_antes', 'dados_depois', 'ip', 'user_agent', 'created_at', 'hash_anterior',
        ];
        $conteudo = [];
        foreach ($campos as $campo) {
            $conteudo[$campo] = $registro->{$campo};
        }

        return hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array{ok: false, total: int, registro_invalido: ?int, motivo: string, ultimo_hash: ?string} */
    private function falha(int $total, ?int $registroId, string $motivo, ?string $ultimoHash): array
    {
        return [
            'ok' => false,
            'total' => $total,
            'registro_invalido' => $registroId,
            'motivo' => $motivo,
            'ultimo_hash' => $ultimoHash,
        ];
    }
}
