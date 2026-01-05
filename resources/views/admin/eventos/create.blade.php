@extends('layouts.app')

@section('title', 'Novo Evento')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-event"></i> Criar Novo Evento</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.eventos.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo_evento" class="form-label">Código do Evento</label>
                        <input type="text" class="form-control @error('codigo_evento') is-invalid @enderror" 
                               id="codigo_evento" name="codigo_evento" value="{{ old('codigo_evento') }}" required>
                        @error('codigo_evento')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <input type="text" class="form-control @error('descricao') is-invalid @enderror" 
                               id="descricao" name="descricao" value="{{ old('descricao') }}" required>
                        @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_dias" name="exige_dias" {{ old('exige_dias') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_dias">Exige Dias</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_valor" name="exige_valor" {{ old('exige_valor') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_valor">Exige Valor</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="valor_minimo" class="form-label">Valor Mínimo</label>
                        <input type="number" step="0.01" class="form-control @error('valor_minimo') is-invalid @enderror" 
                               id="valor_minimo" name="valor_minimo" value="{{ old('valor_minimo') }}">
                        @error('valor_minimo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="valor_maximo" class="form-label">Valor Máximo</label>
                        <input type="number" step="0.01" class="form-control @error('valor_maximo') is-invalid @enderror" 
                               id="valor_maximo" name="valor_maximo" value="{{ old('valor_maximo') }}">
                        @error('valor_maximo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="dias_maximo" class="form-label">Dias Máximo</label>
                        <input type="number" class="form-control @error('dias_maximo') is-invalid @enderror" 
                               id="dias_maximo" name="dias_maximo" value="{{ old('dias_maximo') }}">
                        @error('dias_maximo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_observacao" name="exige_observacao" {{ old('exige_observacao') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_observacao">Exige Observação</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_porcentagem" name="exige_porcentagem" {{ old('exige_porcentagem') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_porcentagem">Exige Porcentagem (Insalubridade)</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="ativo" name="ativo" {{ old('ativo') ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Criar Evento
                    </button>
                    <a href="{{ route('admin.eventos.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
