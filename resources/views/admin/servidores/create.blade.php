@extends('layouts.app')

@section('title', 'Novo Servidor')

@section('content')
<div class="container py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-person-plus" aria-hidden="true"></i> Novo servidor</h1>
            <p class="text-muted mb-0">Cadastre a pessoa, o vínculo e a lotação inicial em uma única operação.</p>
        </div>
        <a href="{{ route('admin.servidores.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            Revise os campos destacados antes de salvar.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.servidores.store') }}">
        @csrf
        <div class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Identificação</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label for="matricula" class="form-label">Matrícula <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('matricula') is-invalid @enderror" id="matricula" name="matricula" value="{{ old('matricula') }}" maxlength="50" required autofocus>
                    @error('matricula')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control @error('cpf') is-invalid @enderror" id="cpf" name="cpf" value="{{ old('cpf') }}" inputmode="numeric" maxlength="14" autocomplete="off">
                    @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="data_admissao" class="form-label">Data de admissão <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('data_admissao') is-invalid @enderror" id="data_admissao" name="data_admissao" value="{{ old('data_admissao') }}" max="{{ now()->toDateString() }}" required>
                    @error('data_admissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="nome" class="form-label">Nome completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" maxlength="255" autocomplete="name" required>
                    @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Situação funcional inicial</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label for="tipo_vinculo" class="form-label">Vínculo <span class="text-danger">*</span></label>
                    <select class="form-select @error('tipo_vinculo') is-invalid @enderror" id="tipo_vinculo" name="tipo_vinculo" required>
                        <option value="">Selecione...</option>
                        @foreach ($tiposVinculo as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo_vinculo') === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                    @error('tipo_vinculo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label for="cargo" class="form-label">Cargo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('cargo') is-invalid @enderror" id="cargo" name="cargo" value="{{ old('cargo') }}" maxlength="150" required>
                    @error('cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="carga_horaria" class="form-label">Horas semanais <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('carga_horaria') is-invalid @enderror" id="carga_horaria" name="carga_horaria" value="{{ old('carga_horaria') }}" min="1" max="80" required>
                    @error('carga_horaria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="setor_id" class="form-label">Lotação inicial <span class="text-danger">*</span></label>
                    <select class="form-select @error('setor_id') is-invalid @enderror" id="setor_id" name="setor_id" required>
                        <option value="">Selecione um setor...</option>
                        @foreach ($setores as $setor)
                            <option value="{{ $setor->id }}" @selected((string) old('setor_id') === (string) $setor->id)>{{ $setor->nome }}</option>
                        @endforeach
                    </select>
                    @error('setor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="ato_referencia" class="form-label">Ato ou documento de referência</label>
                    <input type="text" class="form-control @error('ato_referencia') is-invalid @enderror" id="ato_referencia" name="ato_referencia" value="{{ old('ato_referencia') }}" maxlength="255">
                    @error('ato_referencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
            <a href="{{ route('admin.servidores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle" aria-hidden="true"></i> Cadastrar servidor</button>
        </div>
    </form>
</div>
@endsection
