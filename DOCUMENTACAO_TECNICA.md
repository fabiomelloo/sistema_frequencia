# Sistema de Frequência - Documentação Técnica

> **Sistema de Lançamentos Setoriais para Folha de Pagamento**  
> Documentação técnica completa para equipe de desenvolvimento

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Stack Tecnológica](#stack-tecnológica)
3. [Arquitetura do Sistema](#arquitetura-do-sistema)
4. [Estrutura do Projeto](#estrutura-do-projeto)
5. [Modelos de Dados](#modelos-de-dados)
6. [Fluxo de Lançamentos](#fluxo-de-lançamentos)
7. [Autenticação e Autorização](#autenticação-e-autorização)
8. [API e Rotas](#api-e-rotas)
9. [Services e Regras de Negócio](#services-e-regras-de-negócio)
10. [Deploy e Infraestrutura](#deploy-e-infraestrutura)
11. [Comandos Úteis](#comandos-úteis)

---

## 1. Visão Geral

O **Sistema de Frequência** é uma aplicação web desenvolvida para gerenciar lançamentos setoriais de frequência de servidores públicos. O sistema permite que setores façam lançamentos de eventos de folha (adicionais, insalubridade, etc.), que são posteriormente conferidos pela central e exportados para integração com sistemas de folha de pagamento.

### Principais Funcionalidades

- ✅ Cadastro e gerenciamento de **Setores**
- ✅ Cadastro e gerenciamento de **Servidores**
- ✅ Cadastro e gerenciamento de **Eventos de Folha**
- ✅ **Lançamentos Setoriais** com validação de regras de negócio
- ✅ **Painel de Conferência** para aprovação/rejeição
- ✅ **Exportação TXT** para sistemas de folha
- ✅ Controle de **Permissões** por setor/evento
- ✅ Sistema de **Autenticação** com roles (SETORIAL/CENTRAL)

---

## 2. Stack Tecnológica

### Backend
| Tecnologia | Versão | Descrição |
|------------|--------|-----------|
| **PHP** | 8.2+ | Linguagem de programação |
| **Laravel** | 11.x | Framework PHP |
| **PostgreSQL** | 15 | Banco de dados relacional |
| **Composer** | 2.x | Gerenciador de dependências PHP |

### Frontend
| Tecnologia | Descrição |
|------------|-----------|
| **Blade** | Template engine do Laravel |
| **Bootstrap** | Framework CSS (presumido) |
| **JavaScript** | Interatividade no frontend |

### Infraestrutura
| Tecnologia | Descrição |
|------------|-----------|
| **Docker** | Containerização |
| **Nginx** | Servidor web (reverse proxy) |
| **PHP-FPM** | Gerenciador de processos PHP |

### Dependências Principais
```json
{
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "doctrine/dbal": "^4.4",
    "laravel/tinker": "^2.8"
}
```

---

## 3. Arquitetura do Sistema

### Diagrama de Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│                         NGINX (porta 8000)                       │
│                    Reverse Proxy / Arquivos Estáticos            │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PHP-FPM (porta 9000)                        │
│                        Laravel Application                       │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ Controllers │  │   Models    │  │       Services          │  │
│  │             │  │             │  │                         │  │
│  │ - Auth      │  │ - User      │  │ - ValidacaoLancamento   │  │
│  │ - Lancamento│  │ - Servidor  │  │ - RegrasLancamento      │  │
│  │ - Painel    │  │ - Setor     │  │ - GeradorTxtFolha       │  │
│  │ - Admin     │  │ - Evento    │  │                         │  │
│  │ - Servidor  │  │ - Lancamento│  │                         │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   PostgreSQL (porta 5432)                        │
│                      Database: frequencia                        │
└─────────────────────────────────────────────────────────────────┘
```

### Padrão MVC + Services

O sistema segue o padrão **MVC** (Model-View-Controller) com uma camada adicional de **Services** para lógica de negócio complexa:

- **Models**: Representação das tabelas do banco e relacionamentos
- **Controllers**: Recebem requisições e coordenam responses
- **Services**: Contém regras de negócio complexas e validações
- **Views (Blade)**: Templates para renderização do frontend

---

## 4. Estrutura do Projeto

```
sistema_frequencia/
├── app/
│   ├── Enums/                    # Enumerações PHP 8.1+
│   │   ├── LancamentoStatus.php  # Status: PENDENTE, CONFERIDO, REJEITADO, EXPORTADO
│   │   └── TipoEvento.php        # Tipos de evento de folha
│   │
│   ├── Http/
│   │   ├── Controllers/          # Controllers da aplicação
│   │   │   ├── AuthController.php
│   │   │   ├── LancamentoSetorialController.php
│   │   │   ├── PainelConferenciaController.php
│   │   │   ├── EventoController.php
│   │   │   ├── ServidorController.php
│   │   │   ├── SetorController.php
│   │   │   ├── UsersController.php
│   │   │   ├── PermissaoController.php
│   │   │   └── PerfilController.php
│   │   │
│   │   ├── Middleware/           # Middlewares customizados
│   │   └── Requests/             # Form Requests para validação
│   │
│   ├── Models/                   # Eloquent Models
│   │   ├── User.php
│   │   ├── Servidor.php
│   │   ├── Setor.php
│   │   ├── EventoFolha.php
│   │   ├── LancamentoSetorial.php
│   │   └── ExportacaoFolha.php
│   │
│   └── Services/                 # Camada de serviços
│       ├── ValidacaoLancamentoService.php
│       ├── RegrasLancamentoService.php
│       └── GeradorTxtFolhaService.php
│
├── database/
│   ├── migrations/               # 14 migrações do schema
│   └── seeders/                  # Seeders de dados iniciais
│
├── resources/
│   └── views/                    # Templates Blade
│       ├── admin/                # Views administrativas
│       ├── auth/                 # Login/Logout
│       ├── lancamentos/          # CRUD de lançamentos
│       ├── painel/               # Painel de conferência
│       ├── layouts/              # Templates base
│       ├── perfil/               # Perfil do usuário
│       └── users/                # Gerenciamento de usuários
│
├── routes/
│   ├── web.php                   # Rotas web (principal)
│   └── api.php                   # Rotas API
│
├── docker/
│   └── nginx/
│       └── default.conf          # Configuração do Nginx
│
├── docker-compose.yml            # Orquestração Docker
├── Dockerfile                    # Build da imagem PHP
└── .env                          # Variáveis de ambiente
```

---

## 5. Modelos de Dados

### Diagrama Entidade-Relacionamento

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│      SETORES     │       │     USUARIOS     │       │   SERVIDORES     │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │       │ id               │
│ nome             │◄──────│ setor_id         │       │ matricula        │
│ sigla            │       │ name             │       │ nome             │
│ ativo            │       │ email            │       │ setor_id         │──────►
└────────┬─────────┘       │ role (ENUM)      │       │ funcao_vigia     │
         │                 │ ...              │       │ trabalha_noturno │
         │                 └────────┬─────────┘       │ ativo            │
         │                          │                 │ origem_registro  │
         ▼                          │                 └────────┬─────────┘
┌──────────────────┐                │                          │
│   EVENTO_SETOR   │                │                          │
│   (Permissões)   │                ▼                          ▼
├──────────────────┤       ┌────────────────────────────────────────────┐
│ setor_id         │       │           LANCAMENTOS_SETORIAIS            │
│ evento_id        │       ├────────────────────────────────────────────┤
│ ativo            │       │ id                                         │
└────────┬─────────┘       │ servidor_id ───────────────────────────────┤
         │                 │ evento_id ─────────────────────────────────┤
         ▼                 │ setor_origem_id                            │
┌──────────────────┐       │ dias_trabalhados                           │
│  EVENTOS_FOLHA   │       │ dias_noturnos                              │
├──────────────────┤       │ valor                                      │
│ id               │       │ valor_gratificacao                         │
│ codigo_evento    │◄──────│ porcentagem_insalubridade                  │
│ tipo_evento      │       │ porcentagem_periculosidade                 │
│ descricao        │       │ adicional_turno                            │
│ exige_dias       │       │ adicional_noturno                          │
│ exige_valor      │       │ observacao                                 │
│ valor_minimo     │       │ status (ENUM)                              │
│ valor_maximo     │       │ motivo_rejeicao                            │
│ dias_maximo      │       │ id_validador ─────────────────────────►    │
│ exige_observacao │       │ validated_at                               │
│ exige_porcentagem│       │ exportado_em                               │
│ ativo            │       └────────────────────────────────────────────┘
└──────────────────┘
```

### Detalhamento dos Models

#### `User` (Usuário)
```php
// Campos principais
- id, name, email, password
- role: 'SETORIAL' | 'CENTRAL'
- setor_id: FK para setores (obrigatório para SETORIAL)
```

#### `Setor`
```php
protected $fillable = ['nome', 'sigla', 'ativo'];

// Relacionamentos
usuarios()          → HasMany User
servidores()        → HasMany Servidor
eventosPermitidos() → BelongsToMany EventoFolha (via evento_setor)
lancamentos()       → HasMany LancamentoSetorial
```

#### `Servidor`
```php
protected $fillable = [
    'matricula',
    'nome',
    'setor_id',
    'origem_registro',
    'ativo',
    'funcao_vigia',      // Boolean - permite adicional de turno
    'trabalha_noturno'   // Boolean - permite adicional noturno
];

// Relacionamentos
setor()             → BelongsTo Setor
lancamentos()       → HasMany LancamentoSetorial
lancamentosAtivos() → HasMany (filtra status)
```

#### `EventoFolha`
```php
protected $fillable = [
    'codigo_evento',    // Código para exportação TXT
    'tipo_evento',      // Enum TipoEvento
    'descricao',
    'exige_dias',       // Boolean
    'exige_valor',      // Boolean
    'valor_minimo',     // Decimal
    'valor_maximo',     // Decimal
    'dias_maximo',      // Integer
    'exige_observacao', // Boolean
    'exige_porcentagem',// Boolean
    'ativo'
];

protected $casts = [
    'tipo_evento' => TipoEvento::class
];

// Métodos
temDireitoNoSetor($setorId) → Verifica permissão
```

#### `LancamentoSetorial`
```php
protected $fillable = [
    'servidor_id',
    'evento_id',
    'setor_origem_id',
    'dias_trabalhados',
    'dias_noturnos',
    'valor',
    'valor_gratificacao',
    'porcentagem_insalubridade',
    'porcentagem_periculosidade',
    'adicional_turno',
    'adicional_noturno',
    'observacao',
    'status',              // PENDENTE, CONFERIDO, REJEITADO, EXPORTADO
    'motivo_rejeicao',
    'id_validador',
    'validated_at',
    'exportado_em'
];

// Métodos de Status
isPendente()      → Boolean
isConferido()     → Boolean
isRejeitado()     → Boolean
isExportado()     → Boolean
podeSerEditado()  → Boolean (true se PENDENTE)
```

---

## 6. Fluxo de Lançamentos

### Estados do Lançamento (Enum `LancamentoStatus`)

```
┌─────────────┐     Aprovar      ┌─────────────┐     Exportar     ┌─────────────┐
│  PENDENTE   │ ─────────────►   │  CONFERIDO  │ ─────────────►   │  EXPORTADO  │
└─────────────┘                  └─────────────┘                  └─────────────┘
      │                                ▲
      │ Rejeitar                       │
      ▼                                │
┌─────────────┐                        │
│  REJEITADO  │ ───── Corrigir ────────┘
└─────────────┘     (novo lançamento)
```

### Fluxo Completo

1. **Usuário SETORIAL** cria lançamento → Status: `PENDENTE`
2. **Usuário CENTRAL** visualiza no painel de conferência
3. **Decisão**:
   - ✅ **Aprovar** → Status: `CONFERIDO`
   - ❌ **Rejeitar** → Status: `REJEITADO` (com motivo)
4. **Exportação** → Status: `EXPORTADO`, gera arquivo TXT

### Tipos de Evento (Enum `TipoEvento`)

| Valor | Label | Requisitos |
|-------|-------|------------|
| `ADICIONAL_TURNO` | Adicional de Turno | Servidor deve ter `funcao_vigia = true` |
| `ADICIONAL_NOTURNO` | Adicional Noturno | Servidor deve ter `trabalha_noturno = true` |
| `INSALUBRIDADE` | Insalubridade | Não pode coexistir com Periculosidade |
| `PERICULOSIDADE` | Periculosidade | Não pode coexistir com Insalubridade |
| `GRATIFICACAO` | Gratificação | - |
| `FREQUENCIA` | Frequência | - |
| `OUTROS` | Outros | - |

---

## 7. Autenticação e Autorização

### Roles do Sistema

| Role | Descrição | Permissões |
|------|-----------|------------|
| `SETORIAL` | Usuário de setor | CRUD de lançamentos do próprio setor |
| `CENTRAL` | Administrador central | Conferência, exportação, gestão completa |

### Middlewares

```php
// Middleware de autenticação padrão Laravel
Route::middleware(['auth'])->group(function () {
    // Rotas autenticadas
});

// Middleware de role customizado
Route::middleware(['role:SETORIAL'])->group(function () {
    // Rotas exclusivas para setoriais
});

Route::middleware(['role:CENTRAL'])->group(function () {
    // Rotas exclusivas para central
});
```

### Fluxo de Login

1. Usuário acessa `/login`
2. Submete credenciais (email/senha)
3. Sistema valida via `AuthController::login()`
4. Redirecionamento baseado no role:
   - `SETORIAL` → `/lancamentos`
   - `CENTRAL` → `/painel`

---

## 8. API e Rotas

### Rotas Web Principais

#### Públicas
```
GET  /login          → AuthController@showLoginForm
POST /login          → AuthController@login
POST /logout         → AuthController@logout
```

#### Setoriais (role: SETORIAL)
```
GET    /lancamentos           → LancamentoSetorialController@index
GET    /lancamentos/create    → LancamentoSetorialController@create
POST   /lancamentos           → LancamentoSetorialController@store
GET    /lancamentos/{id}      → LancamentoSetorialController@show
GET    /lancamentos/{id}/edit → LancamentoSetorialController@edit
PUT    /lancamentos/{id}      → LancamentoSetorialController@update
DELETE /lancamentos/{id}      → LancamentoSetorialController@destroy
```

#### Central (role: CENTRAL)
```
# Painel de Conferência
GET  /painel                     → PainelConferenciaController@index
GET  /painel/{id}                → PainelConferenciaController@show
POST /painel/{id}/aprovar        → PainelConferenciaController@aprovar
POST /painel/{id}/rejeitar       → PainelConferenciaController@rejeitar
POST /painel/exportar            → PainelConferenciaController@exportar

# Administração
GET|POST|PUT|DELETE /admin/users      → UsersController (resource)
GET|POST|PUT|DELETE /admin/setores    → SetorController (resource)
GET|POST|PUT|DELETE /admin/servidores → ServidorController (resource)
GET|POST|PUT|DELETE /admin/eventos    → EventoController (resource)

# Permissões Setor-Evento
GET    /admin/permissoes              → PermissaoController@index
POST   /admin/permissoes              → PermissaoController@store
DELETE /admin/permissoes/{setor}/{evento} → PermissaoController@destroy
POST   /admin/permissoes/{setor}/{evento}/toggle → PermissaoController@toggle
```

#### Perfil (todos autenticados)
```
GET  /perfil → PerfilController@show
PUT  /perfil → PerfilController@update
```

---

## 9. Services e Regras de Negócio

### `ValidacaoLancamentoService`

Responsável por validar as regras de negócio antes de salvar um lançamento.

```php
class ValidacaoLancamentoService
{
    // Método principal
    public function validarRegrasNegocio(array $data): array
    
    // Validações específicas
    public function podeAplicarAdicionalTurno(Servidor $servidor, EventoFolha $evento): bool
    public function podeAplicarAdicionalNoturno(Servidor $servidor, EventoFolha $evento): bool
    public function validarInsalubridadePericulosidade(?int $insalubridade, ?int $periculosidade): bool
    public function validarCoerenciaDiasAdicionais(...): bool
}
```

**Regras Implementadas:**

| Regra | Descrição |
|-------|-----------|
| Adicional de Turno | Só para servidores com `funcao_vigia = true` |
| Adicional Noturno | Só para servidores com `trabalha_noturno = true` |
| Insalubridade/Periculosidade | Mutuamente exclusivos |
| Dias Noturnos | Não pode exceder dias trabalhados |
| Dias Trabalhados | Não pode exceder dias do mês |
| Coerência | Dias trabalhados devem ter valor/adicional correspondente |

### `GeradorTxtFolhaService`

Gera arquivo TXT para integração com sistema de folha.

```php
class GeradorTxtFolhaService
{
    // Constantes de formatação
    private const TAMANHO_CODIGO_EVENTO = 10;
    private const TAMANHO_MATRICULA = 13;
    private const TAMANHO_VALOR = 14;
    private const TAMANHO_LINHA = 37;
    
    // Método principal
    public function gerar(): array
    // Retorna: ['nomeArquivo', 'idsExportados', 'exportacaoId']
}
```

**Formato da Linha TXT:**
```
[CODIGO_EVENTO:10][MATRICULA:13][VALOR:14] = 37 caracteres
```

Exemplo:
```
0000001234000000012345600000000150000
```

### `RegrasLancamentoService`

Contém regras adicionais de negócio para lançamentos.

---

## 10. Deploy e Infraestrutura

### Docker Compose

O sistema utiliza 3 containers:

```yaml
services:
  app:    # PHP-FPM 8.4
  web:    # Nginx 1.23
  db:     # PostgreSQL 15
```

### Dockerfile

- Base: `php:8.4-fpm`
- Extensões: `pdo_pgsql`, `zip`, `gd`, `bcmath`, `mbstring`, `pcntl`
- Composer 2.x incluído

### Variáveis de Ambiente (.env)

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=frequencia
DB_USERNAME=postgres
DB_PASSWORD=1234
```

### Portas Expostas

| Serviço | Porta Interna | Porta Externa |
|---------|---------------|---------------|
| Nginx | 80 | 8000 |
| PostgreSQL | 5432 | 5432 |
| PHP-FPM | 9000 | - |

---

## 11. Comandos Úteis

### Docker

```bash
# Subir ambiente
docker-compose up -d

# Parar ambiente
docker-compose down

# Ver logs
docker-compose logs -f app

# Acessar container PHP
docker exec -it sistema_frequencia_app bash

# Acessar banco de dados
docker exec -it sistema_frequencia_db psql -U postgres -d frequencia
```

### Laravel (dentro do container)

```bash
# Rodar migrations
php artisan migrate

# Rodar seeders
php artisan db:seed

# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Gerar chave
php artisan key:generate

# Listar rotas
php artisan route:list

# Tinker (REPL)
php artisan tinker
```

### Composer

```bash
# Instalar dependências
composer install

# Atualizar dependências
composer update

# Autoload
composer dump-autoload
```

---

## 📞 Contato

Para dúvidas técnicas sobre o sistema, entre em contato com a equipe de desenvolvimento.

---

> **Última atualização:** Fevereiro 2026  
> **Versão do Documento:** 1.0
