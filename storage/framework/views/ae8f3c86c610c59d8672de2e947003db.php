

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Meus Lançamentos</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('lancamentos.create')); ?>" class="btn btn-primary">Novo Lançamento</a>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matrícula</th>
                        <th>Servidor</th>
                        <th>Evento</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $lancamentos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lancamento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($lancamento->servidor->matricula); ?></td>
                            <td><?php echo e($lancamento->servidor->nome); ?></td>
                            <td><?php echo e($lancamento->evento->descricao); ?></td>
                            <td>
                                <?php if($lancamento->isPendente()): ?>
                                    <span class="badge bg-warning text-dark">PENDENTE</span>
                                <?php elseif($lancamento->isConferido()): ?>
                                    <span class="badge bg-success">CONFERIDO</span>
                                <?php elseif($lancamento->isRejeitado()): ?>
                                    <span class="badge bg-danger">REJEITADO</span>
                                <?php elseif($lancamento->isExportado()): ?>
                                    <span class="badge bg-secondary">EXPORTADO</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($lancamento->created_at->format('d/m/Y H:i')); ?></td>
                            <td>
                                <a href="<?php echo e(route('lancamentos.show', $lancamento)); ?>" class="btn btn-sm btn-info" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if($lancamento->podeSerEditado()): ?>
                                    <a href="<?php echo e(route('lancamentos.edit', $lancamento)); ?>" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="<?php echo e(route('lancamentos.destroy', $lancamento)); ?>" method="POST" style="display: inline;">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-sm btn-danger" title="Deletar" onclick="return confirm('Tem certeza?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                Nenhum lançamento encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($lancamentos->hasPages()): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <?php echo e($lancamentos->links()); ?>

        </nav>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/lancamentos/index.blade.php ENDPATH**/ ?>