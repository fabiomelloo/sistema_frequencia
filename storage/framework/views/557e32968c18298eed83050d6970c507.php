

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Editar Lançamento</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('lancamentos.index')); ?>" class="btn btn-secondary">Voltar</a>
        </div>
    </div>

    <?php if($errors->any()): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Erros encontrados:</strong>
            <ul class="mb-0">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?php echo e(route('lancamentos.update', $lancamento)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="servidor_id" class="form-label">Matrícula do Servidor <span class="text-danger">*</span></label>
                        <select name="servidor_id" id="servidor_id" class="form-control <?php $__errorArgs = ['servidor_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                            <option value="">-- Selecione --</option>
                            <?php $__currentLoopData = $servidores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $servidor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($servidor->id); ?>" <?php if($lancamento->servidor_id == $servidor->id): echo 'selected'; endif; ?>>
                                    <?php echo e($servidor->matricula); ?> - <?php echo e($servidor->nome); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <?php $__errorArgs = ['servidor_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div class="col-md-6">
                        <label for="evento_id" class="form-label">Evento <span class="text-danger">*</span></label>
                        <select name="evento_id" id="evento_id" class="form-control <?php $__errorArgs = ['evento_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" required>
                            <option value="">-- Selecione --</option>
                            <?php $__currentLoopData = $eventos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($evento->id); ?>" data-exige-dias="<?php echo e($evento->exige_dias ? 1 : 0); ?>" data-exige-valor="<?php echo e($evento->exige_valor ? 1 : 0); ?>" data-exige-observacao="<?php echo e($evento->exige_observacao ? 1 : 0); ?>" data-exige-porcentagem="<?php echo e($evento->exige_porcentagem ? 1 : 0); ?>" <?php if($lancamento->evento_id == $evento->id): echo 'selected'; endif; ?>>
                                    <?php echo e($evento->codigo_evento); ?> - <?php echo e($evento->descricao); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <?php $__errorArgs = ['evento_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row mb-3" id="dias-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="dias_trabalhados" class="form-label">Dias Trabalhados</label>
                        <input type="number" name="dias_trabalhados" id="dias_trabalhados" class="form-control <?php $__errorArgs = ['dias_trabalhados'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" min="0" value="<?php echo e(old('dias_trabalhados', $lancamento->dias_trabalhados)); ?>">
                        <?php $__errorArgs = ['dias_trabalhados'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row mb-3" id="valor-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="number" name="valor" id="valor" class="form-control <?php $__errorArgs = ['valor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" step="0.01" min="0" value="<?php echo e(old('valor', $lancamento->valor)); ?>">
                        <?php $__errorArgs = ['valor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row mb-3" id="porcentagem-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="porcentagem_insalubridade" class="form-label">Porcentagem de Insalubridade (%) <span class="text-danger">*</span></label>
                        <select name="porcentagem_insalubridade" id="porcentagem_insalubridade" class="form-control <?php $__errorArgs = ['porcentagem_insalubridade'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                            <option value="">-- Selecione --</option>
                            <option value="10" <?php if(old('porcentagem_insalubridade', $lancamento->porcentagem_insalubridade) == 10): echo 'selected'; endif; ?>>10%</option>
                            <option value="20" <?php if(old('porcentagem_insalubridade', $lancamento->porcentagem_insalubridade) == 20): echo 'selected'; endif; ?>>20%</option>
                            <option value="40" <?php if(old('porcentagem_insalubridade', $lancamento->porcentagem_insalubridade) == 40): echo 'selected'; endif; ?>>40%</option>
                        </select>
                        <?php $__errorArgs = ['porcentagem_insalubridade'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row mb-3" id="observacao-container" style="display: none;">
                    <div class="col-md-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea name="observacao" id="observacao" class="form-control <?php $__errorArgs = ['observacao'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" rows="3"><?php echo e(old('observacao', $lancamento->observacao)); ?></textarea>
                        <?php $__errorArgs = ['observacao'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="invalid-feedback"><?php echo e($message); ?></span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Atualizar Lançamento</button>
                        <a href="<?php echo e(route('lancamentos.index')); ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventoSelect = document.getElementById('evento_id');
    
    function showRequiredFields() {
        const selected = eventoSelect.options[eventoSelect.selectedIndex];
        const exigeDias = selected.dataset.exigeDias === '1';
        const exigeValor = selected.dataset.exigeValor === '1';
        const exigeObservacao = selected.dataset.exigeObservacao === '1';
        const exigePorcentagem = selected.dataset.exigePorcentagem === '1';
        
        document.getElementById('dias-container').style.display = exigeDias ? 'block' : 'none';
        document.getElementById('valor-container').style.display = exigeValor ? 'block' : 'none';
        document.getElementById('porcentagem-container').style.display = exigePorcentagem ? 'block' : 'none';
        document.getElementById('observacao-container').style.display = exigeObservacao ? 'block' : 'none';
        
        if (exigeDias) document.getElementById('dias_trabalhados').required = true;
        else document.getElementById('dias_trabalhados').required = false;
        
        if (exigeValor) document.getElementById('valor').required = true;
        else document.getElementById('valor').required = false;
        
        if (exigePorcentagem) document.getElementById('porcentagem_insalubridade').required = true;
        else document.getElementById('porcentagem_insalubridade').required = false;
    }
    
    eventoSelect.addEventListener('change', showRequiredFields);
    showRequiredFields();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/lancamentos/edit.blade.php ENDPATH**/ ?>