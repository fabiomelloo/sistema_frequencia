@extends('layouts.app')

@section('title', 'Meu Perfil')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="bi bi-person-circle"></i> Meu Perfil</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('home') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Erros encontrados:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Perfil</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('perfil.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $user->name) }}" 
                                   required>
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $user->email) }}" 
                                   required>
                            @error('email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Perfil</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="{{ $user->role->label() }}" 
                                   disabled>
                            <small class="form-text text-muted">O perfil não pode ser alterado.</small>
                        </div>

                        @if ($user->setor)
                            <div class="mb-3">
                                <label for="setor" class="form-label">Setor</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="setor" 
                                       value="{{ $user->setor->nome }}" 
                                       disabled>
                                <small class="form-text text-muted">O setor não pode ser alterado pelo usuário.</small>
                            </div>
                        @endif

                        <hr class="my-4">
                        <h6 class="mb-3"><i class="bi bi-key"></i> Alterar Senha</h6>
                        <p class="text-muted small">Deixe em branco se não desejar alterar a senha.</p>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nova Senha</label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   autocomplete="new-password">
                            @error('password')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
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
                            <a href="{{ route('home') }}" class="btn btn-secondary">
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
                        {{ $user->created_at->format('d/m/Y H:i') }}
                    </p>
                    @if ($user->email_verified_at)
                        <p class="mb-2">
                            <strong><i class="bi bi-check-circle text-success"></i> E-mail verificado:</strong><br>
                            {{ $user->email_verified_at->format('d/m/Y H:i') }}
                        </p>
                    @else
                        <p class="mb-2">
                            <strong><i class="bi bi-exclamation-circle text-warning"></i> E-mail:</strong><br>
                            <span class="text-warning">Não verificado</span>
                        </p>
                    @endif
                    <p class="mb-0">
                        <strong><i class="bi bi-shield-check"></i> Última atualização:</strong><br>
                        {{ $user->updated_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            @if ($user->isSetorial())
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Setor</h5>
                    </div>
                    <div class="card-body">
                        @if ($user->setor)
                            <p class="mb-1"><strong>Nome:</strong> {{ $user->setor->nome }}</p>
                            <p class="mb-0"><strong>Descrição:</strong> {{ $user->setor->descricao ?? 'Não informado' }}</p>
                        @else
                            <p class="text-muted mb-0">Nenhum setor vinculado</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
