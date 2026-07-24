<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditReadMiddleware
{
    /**
     * Rotas sensíveis que devem ter auditoria de leitura.
     * Formato: 'nome_rota' => ['modelo', 'parametro_id']
     *
     * Nota: Para rotas de listagem (index), o parametro_id é null.
     */
    private array $rotasSensiveis = [
        // Servidores
        'admin.servidores.show' => ['Servidor', 'servidor'],
        'admin.servidores.index' => ['Servidor', null],
        'admin.servidores.historico' => ['Servidor', 'servidor'],

        // Usuários
        'admin.users.index' => ['User', null],

        // Lançamentos Setoriais
        'lancamentos.show' => ['LancamentoSetorial', 'lancamento'],
        'lancamentos.index' => ['LancamentoSetorial', null],

        // Frequencia mensal e ocorrencias
        'frequencia.index' => ['FolhaFrequencia', null],
        'frequencia.show' => ['FolhaFrequencia', 'folha'],
        'ocorrencias.index' => ['OcorrenciaFrequencia', null],
        'ocorrencias.show' => ['OcorrenciaFrequencia', 'ocorrencia'],

        // Painel de Conferência
        'painel.show' => ['LancamentoSetorial', 'lancamento'],
        'painel.index' => ['LancamentoSetorial', null],

        'painel-frequencias.index' => ['FolhaFrequencia', null],
        'painel-frequencias.show' => ['FolhaFrequencia', 'folha'],

        // Relatorios com dados funcionais
        'admin.relatorios.resumo' => ['RelatorioFrequencia', null],
        'admin.relatorios.folha-espelho' => ['Servidor', null],

        // Auditoria
        'admin.audit.index' => ['AuditLog', null],

        // Dashboard (acesso a dados agregados)
        'dashboard' => ['Dashboard', null],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apenas registrar leituras de rotas autenticadas
        if (! auth()->check() || $response->getStatusCode() >= 400) {
            return $response;
        }

        $rotaNome = $request->route()?->getName();

        if (! $rotaNome || ! isset($this->rotasSensiveis[$rotaNome])) {
            return $response;
        }

        [$modelo, $parametroId] = $this->rotasSensiveis[$rotaNome];

        // Obter ID do recurso acessado
        $recursoId = null;
        if ($parametroId) {
            $recurso = $request->route($parametroId);
            $recursoId = is_object($recurso) ? $recurso->id : $recurso;
        }

        // Registrar auditoria de leitura
        try {
            AuditService::leu(
                $modelo,
                $recursoId,
                "Acesso de leitura à rota: {$rotaNome}".
                ($recursoId ? " (ID: {$recursoId})" : ' (listagem)')
            );
        } catch (\Exception $e) {
            // Não interromper a requisição se houver erro na auditoria
            \Log::warning('Erro ao registrar auditoria de leitura', [
                'erro' => $e->getMessage(),
                'rota' => $rotaNome,
            ]);
        }

        return $response;
    }
}
