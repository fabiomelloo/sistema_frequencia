# Estilo e convenções

- PHP PSR-4, namespace App\.
- Laravel/Eloquent convencional: models singulares em PascalCase, tabelas em snake_case, controllers por recurso, Form Requests para validação, Policies para autorização.
- Métodos e variáveis em camelCase; nomes de domínio e mensagens em português.
- Tipos de retorno e parâmetros são usados amplamente; enums PHP backed por string representam estados/tipos.
- Serviços concentram regras de negócio complexas; controllers orquestram request, autorização, auditoria e redirects.
- Operações críticas usam DB::transaction e, em fluxos concorrentes, lockForUpdate.
- AuditService registra ações e mantém cadeia SHA-256; AuditLog é imutável no model.
- Arquivos privados ficam no disk local apontando para storage/app/private.
- Formatter oficial: Laravel Pint.
- Há divergências de Pint atuais em arquivos legados/novos, sobretudo line endings Windows, imports e espaçamento. Não formate em massa sem confirmar escopo por causa do worktree muito alterado.
- Preserve alterações locais existentes; o repositório costuma estar com muitos arquivos modificados/não rastreados.