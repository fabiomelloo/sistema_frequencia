

<?php $__env->startSection('title', 'Gerenciar Permissões'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-shield-check"></i> Gerenciar Permissões</h1>
            <p class="text-muted">Configure quais setores podem lançar quais eventos</p>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo e(session('error')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Adicionar Nova Permissão</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo e(route('admin.permissoes.store')); ?>">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-md-5">
                        <label for="setor_id" class="form-label">Setor</label>
                        <select class="form-select" id="setor_id" name="setor_id" required>
                            <option value="">Selecione um setor...</option>
                            <?php $__currentLoopData = $setores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($setor->id); ?>"><?php echo e($setor->nome); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="evento_id" class="form-label">Evento</label>
                        <select class="form-select" id="evento_id" name="evento_id" required>
                            <option value="">Selecione um evento...</option>
                            <?php $__currentLoopData = $eventos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($evento->id); ?>"><?php echo e($evento->codigo_evento); ?> - <?php echo e($evento->descricao); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Adicionar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Setor</th>
                            <th>Eventos Permitidos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $setores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($setor->nome); ?></strong><br>
                                    <small class="text-muted"><?php echo e($setor->sigla); ?></small>
                                </td>
                                <td>
                                    <?php if($setor->eventosPermitidos->count() > 0): ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php $__currentLoopData = $setor->eventosPermitidos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <span class="badge bg-primary">
                                                    <?php echo e($evento->codigo_evento); ?> - <?php echo e($evento->descricao); ?>

                                                    <form action="<?php echo e(route('admin.permissoes.destroy', ['setor' => $setor->id, 'evento' => $evento->id])); ?>" 
                                                          method="POST" style="display:inline;" 
                                                          onsubmit="return confirm('Remover esta permissão?')">
                                                        <?php echo csrf_field(); ?>
                                                        <?php echo method_field('DELETE'); ?>
                                                        <button type="submit" class="badge bg-danger border-0" style="cursor: pointer;">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                </span>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Nenhum evento permitido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo e(route('admin.setores.show', $setor)); ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver Setor
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Nenhum setor encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/admin/permissoes/index.blade.php ENDPATH**/ ?>