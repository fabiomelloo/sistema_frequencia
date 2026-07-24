# Operação em produção

Este procedimento mantém a aplicação em MySQL e separa cada processo operacional. O arquivo `docker-compose.production.yml` não publica o banco; somente o Nginx fica disponível em `127.0.0.1:8080` para receber tráfego de um proxy TLS instalado no servidor.

## Dependências externas obrigatórias

Antes do go-live, a infraestrutura precisa fornecer:

- servidor Linux com Docker Engine e Compose;
- domínio institucional e certificado HTTPS válido;
- proxy TLS encaminhando para `127.0.0.1:8080` e preservando `X-Forwarded-Proto`;
- destino externo para logs e alertas de indisponibilidade;
- cópia externa dos backups criptografados;
- SMTP institucional, caso notificações por e-mail sejam ativadas;
- política de retenção e ferramenta antimalware para as evidências documentais.

## Segredos e configuração

Crie o arquivo local de produção sem versioná-lo:

```bash
cp .env.production.example .env.production
```

Preencha todos os valores vazios. Gere `APP_KEY` com 32 bytes aleatórios no formato aceito pelo Laravel e use senhas diferentes para o usuário da aplicação, root do MySQL e criptografia de backup. A chave do backup deve ter pelo menos 32 caracteres e `APP_URL` deve começar com `https://`.

O arquivo `.env.production` nunca deve ser enviado ao Git. Faça cópia segura dos segredos no cofre institucional.

## Primeiro deploy

```bash
docker compose --env-file .env.production -f docker-compose.production.yml build
docker compose --env-file .env.production -f docker-compose.production.yml up -d db
docker compose --env-file .env.production -f docker-compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.production.yml up -d
docker compose --env-file .env.production -f docker-compose.production.yml ps
```

Não use `migrate:fresh`, `db:wipe` ou rollback destrutivo em produção. Seed administrativo deve ser executado somente se o procedimento institucional definir credenciais iniciais seguras.

## Verificações de saúde

- `GET /up`: processo PHP respondendo;
- `GET /health/ready`: MySQL, migrations, cache e armazenamento privado prontos;
- `php artisan sistema:verificar-prontidao --producao`: inclui também todas as exigências de configuração de produção.

O endpoint HTTP retorna apenas estados booleanos. Detalhes ficam restritos ao comando executado no servidor.

```bash
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan sistema:verificar-prontidao --producao
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan schedule:list
docker compose --env-file .env.production -f docker-compose.production.yml exec app php artisan queue:failed
```

Monitore HTTP, espaço dos volumes, erros do worker, jobs falhos e idade do último backup. A fila e o scheduler são serviços distintos; não devem ser executados dentro do processo web.

## Deploy de nova versão

Use uma tag imutável em `APP_IMAGE_TAG`. Antes de liberar tráfego:

1. construa a nova imagem;
2. crie e copie para fora do servidor um backup válido;
3. ative o modo de manutenção quando a migration não for compatível com execução concorrente;
4. aplique `php artisan migrate --force` uma única vez;
5. suba `app`, `web`, `worker` e `scheduler` com a mesma tag;
6. valide `/health/ready`, fila e fluxo de login;
7. retire o modo de manutenção.

## Backup

O serviço `backup` cria diariamente um pacote com dump transacional do MySQL e o volume de armazenamento privado. O pacote usa AES-256-CBC com PBKDF2, recebe manifesto SHA-256 e é retido por 30 dias por padrão.

O volume local de backup não é proteção suficiente. Copie os arquivos `.enc` e `.sha256` para armazenamento externo com controle de acesso e retenção institucional. A chave de criptografia deve ficar fora do servidor de backup.

## Teste de restauração

Faça o teste em ambiente isolado, nunca sobre o banco ativo:

```bash
sha256sum -c sistema-frequencia-AAAAMMDDTHHMMSSZ.tar.gz.enc.sha256
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_ENCRYPTION_KEY -in sistema-frequencia-AAAAMMDDTHHMMSSZ.tar.gz.enc -out restauracao.tar.gz
mkdir restauracao
tar -xzf restauracao.tar.gz -C restauracao
tar -xzf restauracao/payload.tar.gz -C restauracao
mysql -h HOST_TESTE -u USUARIO_TESTE -p BANCO_TESTE < restauracao/database.sql
```

Depois, suba uma instância isolada apontando para o banco restaurado, valide a quantidade de competências, servidores, folhas e evidências, execute a prontidão e abra amostras de documentos. Registre data, responsável, duração e resultado do ensaio.

## Rollback

Se a falha estiver somente no código, restaure a tag anterior de todos os serviços e mantenha o schema. Por isso, migrations de produção devem ser compatíveis com a versão anterior durante a janela de implantação.

Se houver corrupção de dados, interrompa gravações, preserve o banco afetado para análise e restaure o último backup validado em uma nova instância. Nunca execute `migrate:rollback` automaticamente como estratégia de deploy.

## Limites ainda externos ao codebase

O repositório está preparado para implantação, mas não cria domínio, certificado, servidor, alertas, cofre de segredos, armazenamento externo ou antivírus. Esses itens precisam ser contratados/configurados e homologados antes de autorizar dados reais.
