<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Registra uma ação de auditoria.
     */
    public static function registrar(
        string $acao,
        string $modelo,
        ?int $modeloId = null,
        ?string $descricao = null,
        ?array $dadosAntes = null,
        ?array $dadosDepois = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'Sistema',
            'acao' => $acao,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'descricao' => $descricao,
            'dados_antes' => $dadosAntes,
            'dados_depois' => $dadosDepois,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Atalhos para ações comuns
     */
    public static function criou(string $modelo, int $id, ?string $descricao = null, ?array $dados = null): AuditLog
    {
        return self::registrar('CRIOU', $modelo, $id, $descricao, null, $dados);
    }

    public static function editou(string $modelo, int $id, ?string $descricao = null, ?array $antes = null, ?array $depois = null): AuditLog
    {
        return self::registrar('EDITOU', $modelo, $id, $descricao, $antes, $depois);
    }

    public static function excluiu(string $modelo, int $id, ?string $descricao = null, ?array $dados = null): AuditLog
    {
        return self::registrar('EXCLUIU', $modelo, $id, $descricao, $dados, null);
    }

    public static function aprovou(string $modelo, int $id, ?string $descricao = null): AuditLog
    {
        return self::registrar('APROVOU', $modelo, $id, $descricao);
    }

    public static function rejeitou(string $modelo, int $id, ?string $descricao = null): AuditLog
    {
        return self::registrar('REJEITOU', $modelo, $id, $descricao);
    }

    public static function exportou(string $modelo, ?int $id = null, ?string $descricao = null): AuditLog
    {
        return self::registrar('EXPORTOU', $modelo, $id, $descricao);
    }

    public static function login(?string $descricao = null): AuditLog
    {
        return self::registrar('LOGIN', 'User', Auth::id(), $descricao);
    }

    public static function logout(?string $descricao = null): AuditLog
    {
        return self::registrar('LOGOUT', 'User', Auth::id(), $descricao);
    }

    public static function estornou(string $modelo, int $id, ?string $descricao = null, ?array $dados = null): AuditLog
    {
        return self::registrar('ESTORNOU', $modelo, $id, $descricao, $dados, null);
    }

    /**
     * Registra edição com diff estruturado campo-a-campo.
     * O resultado em dados_antes/dados_depois contém apenas os campos alterados.
     */
    public static function editouComDiff(string $modelo, int $id, array $antes, array $depois, ?string $descricao = null): AuditLog
    {
        $camposIgnorados = ['updated_at', 'created_at', 'deleted_at'];
        $diff = self::calcularDiff($antes, $depois, $camposIgnorados);

        if (empty($diff['antes']) && empty($diff['depois'])) {
            // Nenhuma alteração real — registra mesmo assim para auditoria
            return self::registrar('EDITOU', $modelo, $id, $descricao ?? 'Sem alterações detectadas', $antes, $depois);
        }

        $camposAlterados = array_keys($diff['antes']);
        $descFinal = $descricao ?? 'Campos alterados: ' . implode(', ', $camposAlterados);

        return self::registrar('EDITOU', $modelo, $id, $descFinal, $diff['antes'], $diff['depois']);
    }

    /**
     * Calcula diferenças campo-a-campo entre dois arrays.
     */
    private static function calcularDiff(array $antes, array $depois, array $ignorar = []): array
    {
        $diffAntes = [];
        $diffDepois = [];

        foreach ($depois as $campo => $valorNovo) {
            if (in_array($campo, $ignorar)) {
                continue;
            }
            $valorAntigo = $antes[$campo] ?? null;
            if ($valorNovo != $valorAntigo) { // comparação loose para lidar com tipos
                $diffAntes[$campo] = $valorAntigo;
                $diffDepois[$campo] = $valorNovo;
            }
        }

        return ['antes' => $diffAntes, 'depois' => $diffDepois];
    }
}
