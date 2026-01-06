

<?php $__env->startSection('title', 'Detalhes do Evento'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-event"></i> <?php echo e($evento->descricao); ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('admin.eventos.edit', $evento)); ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?php echo e(route('admin.eventos.index')); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informações do Evento</h5>
                </div>
                <div class="card-body">
                    <p><strong>Código:</strong> <?php echo e($evento->codigo_evento); ?></p>
                    <p><strong>Descrição:</strong> <?php echo e($evento->descricao); ?></p>
                    <p><strong>Status:</strong> 
                        <?php if($evento->ativo): ?>
                            <span class="badge bg-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inativo</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Exige Dias:</strong> <?php echo e($evento->exige_dias ? 'Sim' : 'Não'); ?></p>
                    <p><strong>Exige Valor:</strong> <?php echo e($evento->exige_valor ? 'Sim' : 'Não'); ?></p>
                    <p><strong>Exige Observação:</strong> <?php echo e($evento->exige_observacao ? 'Sim' : 'Não'); ?></p>
                    <p><strong>Exige Porcentagem:</strong> <?php echo e($evento->exige_porcentagem ? 'Sim' : 'Não'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Limites e Valores</h5>
                </div>
                <div class="card-body">
                    <p><strong>Valor Mínimo:</strong> <?php echo e($evento->valor_minimo ? 'R$ ' . number_format($evento->valor_minimo, 2, ',', '.') : 'N/A'); ?></p>
                    <p><strong>Valor Máximo:</strong> <?php echo e($evento->valor_maximo ? 'R$ ' . number_format($evento->valor_maximo, 2, ',', '.') : 'N/A'); ?></p>
                    <p><strong>Dias Máximo:</strong> <?php echo e($evento->dias_maximo ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Setores com Permissão</h5>
                </div>
                <div class="card-body">
                    <?php if($evento->setoresComDireito->count() > 0): ?>
                        <ul class="list-group">
                            <?php $__currentLoopData = $evento->setoresComDireito; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="list-group-item"><?php echo e($setor->nome); ?> (<?php echo e($setor->sigla); ?>)</li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Nenhum setor tem permissão para este evento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/admin/eventos/show.blade.php ENDPATH**/ ?>