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
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet"
          integrity="sha384-0evzHDMKMKPKTvBYhCsyCK1PiU+NRnKZSTU0DP4B7AlLTHa6qWqSjO37MFlSiLmP"
          crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
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

                        @if (auth()->user()->isSetorial() || auth()->user()->isGestor())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('frequencia.*', 'lancamentos.*', 'ocorrencias.*') ? 'active' : '' }}" href="#" id="lancamentosDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-calendar-check"></i> Frequência
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="lancamentosDropdown">
                                    <li><a class="dropdown-item" href="{{ route('frequencia.index') }}"><i class="bi bi-clipboard2-check me-2"></i>Frequência Mensal</a></li>
                                    <li><a class="dropdown-item" href="{{ route('ocorrencias.index') }}"><i class="bi bi-calendar2-week me-2"></i>Ocorrências</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.index') }}"><i class="bi bi-list-check me-2"></i>Gerenciar</a></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.create') }}"><i class="bi bi-plus-circle me-2"></i>Novo Lançamento</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.lixeira') }}"><i class="bi bi-trash me-2"></i>Lixeira</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('lancamentos.delegacoes.index') }}"><i class="bi bi-person-up me-2"></i>Delegações</a></li>
                                </ul>
                            </li>
                        @endif

                        @if (auth()->user()->isCentral() || auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('painel-frequencias.*') ? 'active' : '' }}" href="{{ route('painel-frequencias.index') }}">
                                    <i class="bi bi-calendar2-check"></i> Frequências
                                </a>
                            </li>
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
                                    <li><a class="dropdown-item" href="{{ route('admin.eventos.index') }}"><i class="bi bi-ui-checks-grid me-2"></i>Itens Mensais</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.permissoes.index') }}"><i class="bi bi-shield-check me-2"></i>Permissões</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.audit.index') }}"><i class="bi bi-journal-text me-2"></i>Auditoria</a></li>
                                    @if(auth()->user()->isAdmin())
                                        <li><a class="dropdown-item" href="{{ route('admin.configuracoes.index') }}"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                                    @endif
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
            crossorigin="anonymous"></script>
    @yield('scripts')
</body>
</html>
