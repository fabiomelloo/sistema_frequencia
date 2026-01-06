

<?php $__env->startSection('title', 'Detalhes do Setor'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-building"></i> <?php echo e($setor->nome); ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('admin.setores.edit', $setor)); ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?php echo e(route('admin.setores.index')); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informações do Setor</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nome:</strong> <?php echo e($setor->nome); ?></p>
                    <p><strong>Sigla:</strong> <span class="badge bg-secondary"><?php echo e($setor->sigla); ?></span></p>
                    <p><strong>Status:</strong> 
                        <?php if($setor->ativo): ?>
                            <span class="badge bg-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inativo</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Estatísticas</h5>
                </div>
                <div class="card-body">
                    <p><strong>Usuários:</strong> <?php echo e($setor->usuarios->count()); ?></p>
                    <p><strong>Servidores:</strong> <?php echo e($setor->servidores->count()); ?></p>
                    <p><strong>Eventos Permitidos:</strong> <?php echo e($eventosPermitidos->count()); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/admin/setores/show.blade.php ENDPATH**/ ?>