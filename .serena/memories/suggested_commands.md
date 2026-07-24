# Comandos úteis (PowerShell/Windows)

Instalação e execução:
- composer install
- Copy-Item .env.example .env
- php artisan key:generate
- php artisan migrate --seed
- php artisan serve

Qualidade:
- php artisan test
- vendor\bin\pint --test
- vendor\bin\pint
- php composer.phar audit
- php -l caminho\arquivo.php

Operação:
- php artisan sistema:verificar-prontidao
- php artisan sistema:verificar-prontidao --producao
- php artisan auditoria:verificar-integridade
- php artisan schedule:list
- php artisan queue:failed
- php artisan route:list
- php artisan optimize:clear

Docker produção (em host com Docker):
- docker compose --env-file .env.production -f docker-compose.production.yml build
- docker compose --env-file .env.production -f docker-compose.production.yml up -d
- docker compose --env-file .env.production -f docker-compose.production.yml ps

Navegação/pesquisa Windows:
- Get-ChildItem -Force
- Set-Location D:\projetos\sistema_frequencia
- rg --files
- rg "padrao" app routes tests
- git status --short
- git diff -- caminho\arquivo.php

Testes exigem MySQL isolado sistema_frequencia_test; não há fallback SQLite.