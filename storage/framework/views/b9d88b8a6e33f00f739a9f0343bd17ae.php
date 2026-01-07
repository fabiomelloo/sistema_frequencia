

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Detalhes do Lançamento</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('lancamentos.index')); ?>" class="btn btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        Informações do Lançamento
                        <?php if($lancamento->isPendente()): ?>
                            <span class="badge bg-warning text-dark float-end">PENDENTE</span>
                        <?php elseif($lancamento->isConferido()): ?>
                            <span class="badge bg-success float-end">CONFERIDO</span>
                        <?php elseif($lancamento->isRejeitado()): ?>
                            <span class="badge bg-danger float-end">REJEITADO</span>
                        <?php elseif($lancamento->isExportado()): ?>
                            <span class="badge bg-secondary float-end">EXPORTADO</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Matrícula do Servidor:</strong><br>
                            <?php echo e($lancamento->servidor->matricula); ?>

                        </div>
                        <div class="col-md-6">
                            <strong>Nome do Servidor:</strong><br>
                            <?php echo e($lancamento->servidor->nome); ?>

                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Setor de Origem:</strong><br>
                            <?php echo e($lancamento->setorOrigem->nome); ?> (<?php echo e($lancamento->setorOrigem->sigla); ?>)
                        </div>
                        <div class="col-md-6">
                            <strong>Evento:</strong><br>
                            <?php echo e($lancamento->evento->codigo_evento); ?> - <?php echo e($lancamento->evento->descricao); ?>

                        </div>
                    </div>

                    <?php if($lancamento->dias_trabalhados): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Dias Trabalhados:</strong><br>
                                <?php echo e($lancamento->dias_trabalhados); ?>

                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($lancamento->valor): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Valor:</strong><br>
                                R$ <?php echo e(number_format($lancamento->valor, 2, ',', '.')); ?>

                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($lancamento->porcentagem_insalubridade): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Porcentagem de Insalubridade:</strong><br>
                                <?php echo e($lancamento->porcentagem_insalubridade); ?>%
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($lancamento->observacao): ?>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Observação:</strong><br>
                                <?php echo e($lancamento->observacao); ?>

                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Data de Lançamento:</strong><br>
                            <?php echo e($lancamento->created_at->format('d/m/Y H:i:s')); ?>

                        </div>
                    </div>

                    <?php if($lancamento->validated_at): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Data de Validação:</strong><br>
                                <?php echo e($lancamento->validated_at->format('d/m/Y H:i:s')); ?>

                            </div>
                            <div class="col-md-6">
                                <strong>Validado por:</strong><br>
                                <?php echo e($lancamento->validador->name ?? 'N/A'); ?>

                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($lancamento->isRejeitado() && $lancamento->motivo_rejeicao): ?>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Motivo da Rejeição:</strong><br>
                                <div class="alert alert-warning mb-0">
                                    <?php echo e($lancamento->motivo_rejeicao); ?>

                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($lancamento->exportado_em): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Exportado em:</strong><br>
                                <?php echo e($lancamento->exportado_em->format('d/m/Y H:i:s')); ?>

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/lancamentos/show.blade.php ENDPATH**/ ?>