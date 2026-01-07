<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Sistema de Frequência'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .main-content {
            padding-top: 30px;
            padding-bottom: 50px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <i class="bi bi-file-earmark-spreadsheet"></i> Sistema Frequência
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(auth()->guard()->check()): ?>
                        <?php if(auth()->user()->isSetorial()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo e(route('lancamentos.index')); ?>">
                                    <i class="bi bi-pencil-square"></i> Meus Lançamentos
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if(auth()->user()->isCentral()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo e(route('painel.index')); ?>">
                                    <i class="bi bi-speedometer2"></i> Painel de Conferência
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-gear"></i> Administração
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.users.index')); ?>">
                                            <i class="bi bi-people"></i> Usuários
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.setores.index')); ?>">
                                            <i class="bi bi-building"></i> Setores
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.servidores.index')); ?>">
                                            <i class="bi bi-person-badge"></i> Servidores
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.eventos.index')); ?>">
                                            <i class="bi bi-calendar-event"></i> Eventos
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('admin.permissoes.index')); ?>">
                                            <i class="bi bi-shield-check"></i> Permissões
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo e(auth()->user()->name); ?>

                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?php echo e(route('perfil.show')); ?>">
                                        <i class="bi bi-person"></i> Meu Perfil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?php echo e(route('logout')); ?>" method="POST" style="display: inline;">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-box-arrow-right"></i> Sair
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo e(route('login')); ?>">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <?php echo $__env->yieldContent('content'); ?>
    </div>

    <footer class="bg-light py-4 border-top mt-5">
        <div class="container text-center">
            <p class="text-muted mb-0">
                Sistema de Frequência - <?php echo e(date('Y')); ?> | 
                <?php if(auth()->guard()->check()): ?>
                    <?php echo e(auth()->user()->role === 'SETORIAL' ? 'Usuário Setorial' : 'Usuário Central'); ?>

                <?php endif; ?>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php echo $__env->yieldContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>