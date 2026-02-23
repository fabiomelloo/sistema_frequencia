<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema de Frequência')</title>
    <meta name="description" content="@yield('description', 'Sistema de Gestão de Frequência e Lançamentos Setoriais - Otimizado para eficiência.')">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="Sistema de Frequência">
    
    <!-- Open Graph / SEO -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Sistema de Frequência')">
    <meta property="og:description" content="@yield('description', 'Sistema de Gestão de Frequência e Lançamentos Setoriais.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="pt_BR">

    <link rel="canonical" href="{{ url()->current() }}">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --bg-color: #f8fafc;
            --text-main: #334155;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            background-color: var(--bg-color);
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
            font-size: 0.95rem;
            -webkit-font-smoothing: antialiased;
        }
        .navbar {
            background: var(--primary-gradient) !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            padding: 0.6rem 1rem;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .main-content {
            padding-top: 32px;
            padding-bottom: 48px;
            min-height: calc(100vh - 140px);
            animation: fadeIn 0.4s ease-out forwards;
        }
        .card {
            border: 1px solid rgba(0,0,0,0.04);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: var(--transition-smooth);
            background: #ffffff;
        }
        .card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }
        .table th {
            font-weight: 600;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .badge {
            font-weight: 500;
            padding: 0.4em 0.75em;
            border-radius: 6px;
        }
        .btn {
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition-smooth);
            padding: 0.5rem 1rem;
            letter-spacing: 0.2px;
        }
        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        footer {
            background: #fff !important;
            box-shadow: 0 -1px 4px rgba(0,0,0,0.04);
        }
        .notification-badge {
            position: absolute;
            top: 2px;
            right: 0px;
            background: #dc3545;
            color: white;
            font-size: 0.65rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .notification-dropdown {
            width: 340px;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-item.unread {
            border-left: 3px solid var(--accent-color);
            background: #f0f7ff;
        }
        .stat-card {
            border-radius: 12px;
            padding: 1.25rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-card {
            border-radius: 12px;
            background: white;
            border: 1px solid #e9ecef;
        }
        .filter-card .card-body {
            padding: 1rem;
        }
        @media (max-width: 768px) {
            .stat-card .stat-number { font-size: 1.5rem; }
            .notification-dropdown { width: 280px; }
        }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Sistema Frequência
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>

                        @if (auth()->user()->isSetorial())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('lancamentos.*') ? 'active' : '' }}" href="#" id="lancamentosDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-pencil-square"></i> Lançamentos
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="lancamentosDropdown">
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.index') }}"><i class="bi bi-list-check me-2"></i>Gerenciar</a></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.create') }}"><i class="bi bi-plus-circle me-2"></i>Novo Lançamento</a></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.importar.form') }}"><i class="bi bi-upload me-2"></i>Importar CSV</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.lixeira') }}"><i class="bi bi-trash me-2"></i>Lixeira</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.delegacoes.index') }}"><i class="bi bi-person-up me-2"></i>Delegações</a></li>
                                </ul>
                            </li>
                        @endif

                        @if (auth()->user()->isCentral())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('painel.*') ? 'active' : '' }}" href="{{ route('painel.index') }}">
                                    <i class="bi bi-clipboard-check"></i> Painel
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.relatorios.*') ? 'active' : '' }}" href="{{ route('admin.relatorios.resumo') }}">
                                    <i class="bi bi-bar-chart"></i> Relatórios
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.*') && !request()->routeIs('admin.relatorios.*') ? 'active' : '' }}" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-gear"></i> Administração
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="{{ route('admin.competencias.index') }}"><i class="bi bi-calendar-check me-2"></i>Competências</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-2"></i>Usuários</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.setores.index') }}"><i class="bi bi-building me-2"></i>Setores</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.servidores.index') }}"><i class="bi bi-person-badge me-2"></i>Servidores</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.eventos.index') }}"><i class="bi bi-calendar-event me-2"></i>Eventos</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.permissoes.index') }}"><i class="bi bi-shield-check me-2"></i>Permissões</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.audit.index') }}"><i class="bi bi-journal-text me-2"></i>Auditoria</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.configuracoes.index') }}"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                                </ul>
                            </li>
                        @endif
                    @endauth
                </ul>

                <ul class="navbar-nav">
                    @auth
                        {{-- Notificações --}}
                        <li class="nav-item dropdown" style="position: relative;">
                            <a class="nav-link" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                @if ($contadorNotificacoes > 0)
                                    <span class="notification-badge">{{ $contadorNotificacoes > 9 ? '9+' : $contadorNotificacoes }}</span>
                                @endif
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notifDropdown">
                                <li class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom">
                                    <strong class="text-dark">Notificações</strong>
                                    @if ($contadorNotificacoes > 0)
                                        <form action="{{ route('notificacoes.ler-todas') }}" method="POST" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Marcar todas</button>
                                        </form>
                                    @endif
                                </li>
                                @forelse ($notificacoesNaoLidas as $notif)
                                    <li>
                                        <form action="{{ route('notificacoes.ler', $notif) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item notification-item unread text-wrap">
                                                <div class="d-flex align-items-start">
                                                    <i class="bi bi-{{ $notif->tipo === 'APROVADO' ? 'check-circle text-success' : ($notif->tipo === 'REJEITADO' ? 'x-circle text-danger' : 'info-circle text-primary') }} me-2 mt-1"></i>
                                                    <div>
                                                        <div class="fw-semibold" style="font-size:0.85rem;">{{ $notif->titulo }}</div>
                                                        <div class="text-muted" style="font-size:0.78rem;">{{ Str::limit($notif->mensagem, 80) }}</div>
                                                        <small class="text-muted">{{ $notif->created_at->diffForHumans() }}</small>
                                                    </div>
                                                </div>
                                            </button>
                                        </form>
                                    </li>
                                @empty
                                    <li class="px-3 py-4 text-center text-muted">
                                        <i class="bi bi-bell-slash" style="font-size:1.5rem"></i>
                                        <div class="mt-1">Sem notificações</div>
                                    </li>
                                @endforelse
                                @if ($contadorNotificacoes > 0)
                                    <li class="border-top text-center py-2">
                                        <a href="{{ route('notificacoes.index') }}" class="text-decoration-none" style="font-size:0.85rem">Ver todas</a>
                                    </li>
                                @endif
                            </ul>
                        </li>

                        {{-- Usuário --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <span class="dropdown-item-text text-muted" style="font-size:0.8rem">
                                        <i class="bi bi-shield me-1"></i>{{ auth()->user()->role->label() }}
                                    </span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('perfil.show') }}">
                                        <i class="bi bi-person me-2"></i>Meu Perfil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('notificacoes.index') }}">
                                        <i class="bi bi-bell me-2"></i>Notificações
                                        @if ($contadorNotificacoes > 0)
                                            <span class="badge bg-danger ms-1">{{ $contadorNotificacoes }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i>Sair
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container-fluid px-4">
            @yield('content')
        </div>
    </main>

    <footer class="py-3 border-top mt-4">
        <div class="container text-center">
            <p class="text-muted mb-0" style="font-size: 0.8rem">
                <strong>ThellenD</strong> &copy; {{ date('Y') }}
                @auth
                    &mdash; {{ auth()->user()->role->label() }}
                @endauth
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>
