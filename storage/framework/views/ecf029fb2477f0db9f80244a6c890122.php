

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Painel de Conferência de Lançamentos</h2>
        </div>
        <div class="col-md-4 text-end">
            <?php if($statusAtual === 'CONFERIDO'): ?>
                <form action="<?php echo e(route('painel.exportar')); ?>" method="POST" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Exportar todos os lançamentos CONFERIDO?')">
                        Exportar TXT
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Erro:</strong>
            <?php echo e($errors->first()); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Filtrar por Status</h5>
            <div class="btn-group" role="group">
                <a href="<?php echo e(route('painel.index', ['status' => 'PENDENTE'])); ?>" class="btn <?php echo e($statusAtual === 'PENDENTE' ? 'btn-danger active' : 'btn-outline-danger'); ?>">
                    PENDENTE <span class="badge bg-danger ms-2"><?php echo e($contadores['PENDENTE']); ?></span>
                </a>
                <a href="<?php echo e(route('painel.index', ['status' => 'CONFERIDO'])); ?>" class="btn <?php echo e($statusAtual === 'CONFERIDO' ? 'btn-success active' : 'btn-outline-success'); ?>">
                    CONFERIDO <span class="badge bg-success ms-2"><?php echo e($contadores['CONFERIDO']); ?></span>
                </a>
                <a href="<?php echo e(route('painel.index', ['status' => 'REJEITADO'])); ?>" class="btn <?php echo e($statusAtual === 'REJEITADO' ? 'btn-warning active' : 'btn-outline-warning'); ?>">
                    REJEITADO <span class="badge bg-warning ms-2"><?php echo e($contadores['REJEITADO']); ?></span>
                </a>
                <a href="<?php echo e(route('painel.index', ['status' => 'EXPORTADO'])); ?>" class="btn <?php echo e($statusAtual === 'EXPORTADO' ? 'btn-secondary active' : 'btn-outline-secondary'); ?>">
                    EXPORTADO <span class="badge bg-secondary ms-2"><?php echo e($contadores['EXPORTADO']); ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matrícula</th>
                        <th>Servidor</th>
                        <th>Evento</th>
                        <th>Setor</th>
                        <th>Valor</th>
                        <th>Data Lançamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $lancamentos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lancamento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr <?php if($lancamento->isPendente()): ?> class="table-danger" <?php elseif($lancamento->isConferido()): ?> class="table-success" <?php elseif($lancamento->isExportado()): ?> class="table-secondary" <?php endif; ?>>
                            <td>
                                <strong><?php echo e($lancamento->servidor->matricula); ?></strong>
                            </td>
                            <td><?php echo e($lancamento->servidor->nome); ?></td>
                            <td><?php echo e($lancamento->evento->descricao); ?></td>
                            <td><?php echo e($lancamento->setorOrigem->sigla ?? $lancamento->setorOrigem->nome); ?></td>
                            <td>
                                <?php if($lancamento->valor): ?>
                                    R$ <?php echo e(number_format($lancamento->valor, 2, ',', '.')); ?>

                                <?php else: ?>
                                    ---
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($lancamento->created_at->format('d/m/Y H:i')); ?></td>
                            <td>
                                <a href="<?php echo e(route('painel.show', $lancamento)); ?>" class="btn btn-sm btn-info" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if($lancamento->isPendente()): ?>
                                    <form action="<?php echo e(route('painel.aprovar', $lancamento)); ?>" method="POST" style="display: inline;">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejeicaoModal<?php echo e($lancamento->id); ?>" title="Rejeitar">
                                        <i class="bi bi-x-circle"></i>
                                    </button>

                                    <div class="modal fade" id="rejeicaoModal<?php echo e($lancamento->id); ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Rejeitar Lançamento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="<?php echo e(route('painel.rejeitar', $lancamento)); ?>" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="motivo" class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                                                            <textarea name="motivo_rejeicao" id="motivo" class="form-control" rows="4" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-danger">Rejeitar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                Nenhum lançamento encontrado com este status.
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/painel/index.blade.php ENDPATH**/ ?>