@extends('layouts.app')

@section('title', 'Editar Servidor')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-person-badge"></i> Editar Servidor</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.servidores.update', $servidor) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="matricula" class="form-label">Matrícula</label>
                    <input type="text" class="form-control @error('matricula') is-invalid @enderror" 
                           id="matricula" name="matricula" value="{{ old('matricula', $servidor->matricula) }}" required>
                    @error('matricula')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" class="form-control @error('nome') is-invalid @enderror" 
                           id="nome" name="nome" value="{{ old('nome', $servidor->nome) }}" required>
                    @error('nome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="setor_id" class="form-label">Setor</label>
                    <select class="form-select @error('setor_id') is-invalid @enderror" 
                            id="setor_id" name="setor_id" required>
                        @foreach ($setores as $setor)
                            <option value="{{ $setor->id }}" {{ old('setor_id', $servidor->setor_id) == $setor->id ? 'selected' : '' }}>
                                {{ $setor->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('setor_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="origem_registro" class="form-label">Origem do Registro (opcional)</label>
                    <input type="text" class="form-control @error('origem_registro') is-invalid @enderror" 
                           id="origem_registro" name="origem_registro" value="{{ old('origem_registro', $servidor->origem_registro) }}">
                    @error('origem_registro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="ativo" name="ativo" {{ old('ativo', $servidor->ativo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Atualizar Servidor
                    </button>
                    <a href="{{ route('admin.servidores.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
