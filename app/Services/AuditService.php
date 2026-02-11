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
}
