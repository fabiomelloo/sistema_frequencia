@extends('layouts.app')

@section('title', 'Editar Servidor')

@section('content')
<div class="container py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-person-badge" aria-hidden="true"></i> Editar identificação</h1>
            <p class="text-muted mb-0">Vínculo, cargo, carga horária e lotação são alterados pelos fluxos com vigência.</p>
        </div>
        <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.servidores.update', $servidor) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="matricula" class="form-label">Matrícula <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('matricula') is-invalid @enderror" id="matricula" name="matricula" value="{{ old('matricula', $servidor->matricula) }}" maxlength="50" required>
                        @error('matricula')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control @error('cpf') is-invalid @enderror" id="cpf" name="cpf" value="{{ old('cpf', $servidor->cpf) }}" inputmode="numeric" maxlength="14">
                        @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="nome" class="form-label">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome', $servidor->nome) }}" maxlength="255" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle" aria-hidden="true"></i> Salvar identificação</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
