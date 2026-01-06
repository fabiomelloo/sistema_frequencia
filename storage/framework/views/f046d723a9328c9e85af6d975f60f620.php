<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo e(route('home')); ?>">
            <i class="bi bi-file-earmark-spreadsheet"></i> Sistema de Frequência
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
                                <i class="bi bi-list-check"></i> Meus Lançamentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo e(route('lancamentos.create')); ?>">
                                <i class="bi bi-plus-circle"></i> Novo Lançamento
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if(auth()->user()->isCentral()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo e(route('painel.index')); ?>">
                                <i class="bi bi-clipboard-check"></i> Painel de Validação
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo e(route('admin.users.index')); ?>">
                            <i class="bi bi-people"></i> Gerenciar Usuários
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo e(auth()->user()->name); ?>

                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo e(route('admin.users.index')); ?>">
                                    <i class="bi bi-gear"></i> Configurações
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?php echo e(route('logout')); ?>" method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php /**PATH /var/www/html/resources/views/layouts/navbar.blade.php ENDPATH**/ ?>