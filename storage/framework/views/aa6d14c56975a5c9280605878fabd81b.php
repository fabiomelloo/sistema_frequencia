

<?php $__env->startSection('title', 'Meu Perfil'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="bi bi-person-circle"></i> Meu Perfil</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo e(route('home')); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Perfil</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo e(route('perfil.update')); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo e(old('name', $user->name)); ?>" 
                                   required>
                            <?php $__errorArgs = ['name'];
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

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo e(old('email', $user->email)); ?>" 
                                   required>
                            <?php $__errorArgs = ['email'];
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

                        <div class="mb-3">
                            <label for="role" class="form-label">Perfil</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="<?php echo e($user->role === 'CENTRAL' ? 'Central' : 'Setorial'); ?>" 
                                   disabled>
                            <small class="form-text text-muted">O perfil não pode ser alterado.</small>
                        </div>

                        <?php if($user->setor): ?>
                            <div class="mb-3">
                                <label for="setor" class="form-label">Setor</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="setor" 
                                       value="<?php echo e($user->setor->nome); ?>" 
                                       disabled>
                                <small class="form-text text-muted">O setor não pode ser alterado pelo usuário.</small>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">
                        <h6 class="mb-3"><i class="bi bi-key"></i> Alterar Senha</h6>
                        <p class="text-muted small">Deixe em branco se não desejar alterar a senha.</p>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Senha</label>
                            <input type="password" 
                                   class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" 
                                   id="password" 
                                   name="password" 
                                   autocomplete="new-password">
                            <?php $__errorArgs = ['password'];
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

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   autocomplete="new-password">
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Salvar Alterações
                            </button>
                            <a href="<?php echo e(route('home')); ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informações</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong><i class="bi bi-calendar"></i> Cadastrado em:</strong><br>
                        <?php echo e($user->created_at->format('d/m/Y H:i')); ?>

                    </p>
                    <?php if($user->email_verified_at): ?>
                        <p class="mb-2">
                            <strong><i class="bi bi-check-circle text-success"></i> E-mail verificado:</strong><br>
                            <?php echo e($user->email_verified_at->format('d/m/Y H:i')); ?>

                        </p>
                    <?php else: ?>
                        <p class="mb-2">
                            <strong><i class="bi bi-exclamation-circle text-warning"></i> E-mail:</strong><br>
                            <span class="text-warning">Não verificado</span>
                        </p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <strong><i class="bi bi-shield-check"></i> Última atualização:</strong><br>
                        <?php echo e($user->updated_at->format('d/m/Y H:i')); ?>

                    </p>
                </div>
            </div>

            <?php if($user->isSetorial()): ?>
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Setor</h5>
                    </div>
                    <div class="card-body">
                        <?php if($user->setor): ?>
                            <p class="mb-1"><strong>Nome:</strong> <?php echo e($user->setor->nome); ?></p>
                            <p class="mb-0"><strong>Descrição:</strong> <?php echo e($user->setor->descricao ?? 'Não informado'); ?></p>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nenhum setor vinculado</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/perfil/show.blade.php ENDPATH**/ ?>