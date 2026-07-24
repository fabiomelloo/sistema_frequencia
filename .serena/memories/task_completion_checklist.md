# Checklist de conclusão de tarefas

1. Preservar o worktree e revisar git status/diff apenas no escopo alterado.
2. Rodar php -l nos PHP alterados.
3. Rodar vendor\bin\pint --test nos arquivos/escopo alterados (evitar formatação global automática sem autorização).
4. Rodar testes Feature relevantes; para mudanças de domínio, preferir também php artisan test completo.
5. Confirmar MySQL de teste disponível; a suíte não usa SQLite.
6. Para mudanças de schema, aplicar/verificar migrations em MySQL.
7. Para mudanças operacionais, executar sistema:verificar-prontidao e auditoria:verificar-integridade.
8. Para rotas/permissões, conferir php artisan route:list e matriz de roles/policies.
9. Para anexos/exportações, validar armazenamento privado, hash e autorização de download.
10. Documentar testes não executados ou falhas ambientais com precisão.