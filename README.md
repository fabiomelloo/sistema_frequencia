# Sistema de Frequencia

Aplicacao Laravel para cadastro funcional, frequencia mensal, evidencias privadas, conferencia em duas etapas e exportacao institucional. O lancamento operacional e feito diretamente no sistema; planilhas nao sao aceitas como entrada.

## Requisitos locais

- PHP 8.2 ou superior;
- Composer 2;
- MySQL 8 ou MariaDB compativel;
- extensoes PHP `bcmath`, `intl`, `mbstring`, `pdo_mysql` e `zip`.

## Execucao local

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

O `.env.example` aponta para o MySQL local na porta `3307`. Ajuste as credenciais antes da migration. O projeto nao possui fallback SQLite.

## Testes e qualidade

```bash
php artisan test
vendor/bin/pint --test
php composer.phar audit
php artisan sistema:verificar-prontidao
```

Os testes usam exclusivamente o schema MySQL isolado `sistema_frequencia_test`.

## Homologacao

O MVP funcional deve ser validado em uma competencia piloto antes de qualquer uso com dados reais. O roteiro de aceite esta em [docs/homologacao-mvp.md](docs/homologacao-mvp.md).

## Producao

O ambiente de producao possui imagem imutavel, Nginx, PHP-FPM, worker, scheduler, MySQL privado, healthchecks e backup criptografado. O procedimento completo, inclusive primeiro deploy, restauracao e rollback, esta em [docs/operacao-producao.md](docs/operacao-producao.md).

## Perfis

- `ADMIN`: usuarios, setores, catalogo de eventos e configuracoes;
- `CENTRAL`: conferencia central, servidores, competencias e relatorios;
- `SETORIAL`: preenchimento e conferencia do proprio setor;
- `GESTOR`: preenchimento e conferencia setorial;
- `AUDITOR`: consulta dos registros de auditoria.

