# Sistema de Frequência — visão geral

Aplicação web institucional para cadastro funcional, apuração de frequência mensal, ocorrências, itens mensais governados, evidências privadas, conferência setorial/central, auditoria e exportação de folha.

Stack: PHP 8.2+, Laravel 12, Eloquent, Blade server-rendered, Bootstrap 5.3/Bootstrap Icons via CDN, CSS próprio, MySQL 8/MariaDB. Não há frontend Node/Vite nem API operacional relevante.

Estrutura:
- app/Models: 25 modelos Eloquent.
- app/Http/Controllers: 26 controllers.
- app/Http/Requests: 37 Form Requests.
- app/Services: 18 serviços de domínio.
- app/Policies: autorização por recurso; middleware role protege grupos.
- app/Enums: estados e tipos de domínio.
- database/migrations: schema incremental.
- resources/views: Blade por módulo.
- routes/web.php: rotas públicas, autenticadas e grupos por perfil.
- tests/Feature: fluxos críticos e operacionais.
- docs: análise do domínio, regras, homologação e operação.

Perfis: ADMIN, CENTRAL, SETORIAL, GESTOR, AUDITOR.

Competência institucional: referência YYYY-MM com período efetivo do dia 11 do mês anterior ao dia 10 do mês da referência.

Fluxo novo: cadastro/histórico funcional -> competência -> folha por setor -> snapshot de servidores/vínculos/designações/vantagens -> ocorrências e itens mensais -> evidências -> finalização -> conferência individual pela Central em rodadas -> aprovação/devolução -> cobertura completa -> fechamento.

Fluxo legado ainda ativo: LancamentoSetorial PENDENTE -> CONFERIDO_SETORIAL -> CONFERIDO -> EXPORTADO, com rejeição/estorno/cancelamento. O exportador TXT ainda usa exclusivamente esse fluxo legado; não há ponte explícita da FolhaFrequencia aprovada para LancamentoSetorial/exportação.

Importações CSV legadas têm código/tabelas, mas são desabilitadas por config e não possuem rotas operacionais.

Operação de produção: Docker Compose com app PHP-FPM, Nginx, worker, scheduler, MySQL 8.4 e backup criptografado. Banco não é publicado; web em localhost:8080 para proxy TLS.