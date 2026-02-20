@extends('layouts.app')

@section('title', 'Transferir Servidor — Sistema de Frequência')
@section('description', 'Transferir servidor entre setores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-arrow-left-right me-2"></i>Transferir Servidor
    </h4>
    <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Erros encontrados:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Servidor</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Nome:</strong> {{ $servidor->nome }}</p>
                <p><strong>Matrícula:</strong> {{ $servidor->matricula }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Setor Atual:</strong> {{ $servidor->setor->nome ?? 'N/A' }}</p>
                <p><strong>Status:</strong> 
                    @if ($servidor->ativo)
                        <span class="badge bg-success">Ativo</span>
                    @else
                        <span class="badge bg-danger">Inativo</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Dados da Transferência</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.servidores.transferir', $servidor) }}">
            @csrf

            <div class="mb-3">
                <label for="novo_setor_id" class="form-label">
                    Novo Setor <span class="text-danger">*</span>
                </label>
                <select class="form-select @error('novo_setor_id') is-invalid @enderror" 
                        id="novo_setor_id" name="novo_setor_id" required>
                    <option value="">Selecione o novo setor</option>
                    @foreach ($setores as $setor)
                        <option value="{{ $setor->id }}" {{ old('novo_setor_id') == $setor->id ? 'selected' : '' }}>
                            {{ $setor->nome }} ({{ $setor->sigla }})
                        </option>
                    @endforeach
                </select>
                @error('novo_setor_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="data_transferencia" class="form-label">
                    Data da Transferência <span class="text-danger">*</span>
                </label>
                <input type="date" 
                       class="form-control @error('data_transferencia') is-invalid @enderror" 
                       id="data_transferencia" 
                       name="data_transferencia" 
                       value="{{ old('data_transferencia', date('Y-m-d')) }}" 
                       required>
                @error('data_transferencia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">A data deve ser hoje ou posterior.</small>
            </div>

            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo da Transferência (opcional)</label>
                <textarea class="form-control @error('motivo') is-invalid @enderror" 
                          id="motivo" 
                          name="motivo" 
                          rows="3" 
                          maxlength="500">{{ old('motivo') }}</textarea>
                @error('motivo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Máximo de 500 caracteres.</small>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Importante:</strong> 
                <ul class="mb-0 mt-2">
                    <li>Será criado histórico de lotação do setor atual até a data de transferência.</li>
                    <li>Será criado histórico de lotação do novo setor a partir da data de transferência.</li>
                    <li>Os lançamentos pendentes serão {{ \App\Models\Configuracao::get('transferir_lancamentos_ao_mudar_setor', 'false') === 'true' ? 'transferidos' : 'mantidos no setor de origem' }}.</li>
                    <li>O setor de origem será notificado sobre a transferência.</li>
                </ul>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Confirmar Transferência
                </button>
                <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
