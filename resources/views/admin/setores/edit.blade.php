@extends('layouts.app')

@section('title', 'Editar Setor')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-building"></i> Editar Setor</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.setores.update', ['setor' => $setor->id]) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome do Setor</label>
                    <input type="text" class="form-control @error('nome') is-invalid @enderror" 
                           id="nome" name="nome" value="{{ old('nome', $setor->nome) }}" required>
                    @error('nome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="sigla" class="form-label">Sigla</label>
                    <input type="text" class="form-control @error('sigla') is-invalid @enderror" 
                           id="sigla" name="sigla" value="{{ old('sigla', $setor->sigla) }}" maxlength="10" required>
                    @error('sigla')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="ativo" name="ativo" {{ old('ativo', $setor->ativo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Atualizar Setor
                    </button>
                    <a href="{{ route('admin.setores.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
