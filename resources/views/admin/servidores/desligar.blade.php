@extends('layouts.app')

@section('title', 'Desligar Servidor — Sistema de Frequência')
@section('description', 'Processar desligamento de servidor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-person-x me-2"></i>Desligar Servidor
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

<div class="card border-danger">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Atenção: Operação Irreversível</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Nome:</strong> {{ $servidor->nome }}</p>
                <p><strong>Matrícula:</strong> {{ $servidor->matricula }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Setor:</strong> {{ $servidor->setor->nome ?? 'N/A' }}</p>
                <p><strong>Status Atual:</strong> 
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

@if ($lancamentos_afetados > 0)
<div class="alert alert-warning mt-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Atenção:</strong> Este servidor possui <strong>{{ $lancamentos_afetados }} lançamento(s) pendente(s)</strong> 
    em competências futuras que serão <strong>cancelados automaticamente</strong> ao desligar o servidor.
</div>
@endif

<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Dados do Desligamento</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.servidores.desligar', $servidor) }}">
            @csrf

            <div class="mb-3">
                <label for="data_desligamento" class="form-label">
                    Data do Desligamento <span class="text-danger">*</span>
                </label>
                <input type="date" 
                       class="form-control @error('data_desligamento') is-invalid @enderror" 
                       id="data_desligamento" 
                       name="data_desligamento" 
                       value="{{ old('data_desligamento', date('Y-m-d')) }}" 
                       required>
                @error('data_desligamento')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">A data deve ser hoje ou posterior.</small>
            </div>

            <div class="mb-3">
                <label for="motivo" class="form-label">
                    Motivo do Desligamento <span class="text-danger">*</span>
                </label>
                <select class="form-select @error('motivo') is-invalid @enderror" 
                        id="motivo" 
                        name="motivo" 
                        required>
                    <option value="">Selecione o motivo</option>
                    <option value="EXONERACAO" {{ old('motivo') == 'EXONERACAO' ? 'selected' : '' }}>Exoneração</option>
                    <option value="APOSENTADORIA" {{ old('motivo') == 'APOSENTADORIA' ? 'selected' : '' }}>Aposentadoria</option>
                    <option value="DEMISSAO" {{ old('motivo') == 'DEMISSAO' ? 'selected' : '' }}>Demissão</option>
                    <option value="OBITO" {{ old('motivo') == 'OBITO' ? 'selected' : '' }}>Óbito</option>
                    <option value="OUTRO" {{ old('motivo') == 'OUTRO' ? 'selected' : '' }}>Outro</option>
                </select>
                @error('motivo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3" id="motivo_detalhado_container" style="display: none;">
                <label for="motivo_detalhado" class="form-label">
                    Motivo Detalhado <span class="text-danger">*</span>
                </label>
                <textarea class="form-control @error('motivo_detalhado') is-invalid @enderror" 
                          id="motivo_detalhado" 
                          name="motivo_detalhado" 
                          rows="3" 
                          maxlength="1000">{{ old('motivo_detalhado') }}</textarea>
                @error('motivo_detalhado')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Máximo de 1000 caracteres.</small>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>O que acontecerá:</strong>
                <ul class="mb-0 mt-2">
                    <li>O servidor será marcado como <strong>inativo</strong>.</li>
                    <li>Lançamentos pendentes de competências <strong>futuras</strong> ao desligamento serão <strong>cancelados automaticamente</strong>.</li>
                    <li>Lançamentos de competências <strong>passadas</strong> serão mantidos para histórico.</li>
                    <li>Os setores afetados serão <strong>notificados</strong> sobre o desligamento.</li>
                </ul>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja desligar este servidor? Esta ação cancelará lançamentos pendentes e notificará os setores afetados.')">
                    <i class="bi bi-check-circle me-1"></i>Confirmar Desligamento
                </button>
                <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('motivo').addEventListener('change', function() {
    const motivoDetalhadoContainer = document.getElementById('motivo_detalhado_container');
    const motivoDetalhado = document.getElementById('motivo_detalhado');
    
    if (this.value === 'OUTRO') {
        motivoDetalhadoContainer.style.display = 'block';
        motivoDetalhado.setAttribute('required', 'required');
    } else {
        motivoDetalhadoContainer.style.display = 'none';
        motivoDetalhado.removeAttribute('required');
        motivoDetalhado.value = '';
    }
});

// Trigger on page load if motivo is already OUTRO
if (document.getElementById('motivo').value === 'OUTRO') {
    document.getElementById('motivo').dispatchEvent(new Event('change'));
}
</script>
@endsection
