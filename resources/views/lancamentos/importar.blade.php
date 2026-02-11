@extends('layouts.app')

@section('title', 'Importar Lançamentos')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold text-primary">
                    <i class="bi bi-upload me-2"></i> Importar Lançamentos (CSV)
                </h5>
            </div>
            <div class="card-body p-4">
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h6 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> Erros na Importação:</h6>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Instruções:</h6>
                    <ul class="mb-0 small">
                        <li>O arquivo deve estar no formato <strong>CSV</strong> (separado por ponto e vírgula).</li>
                        <li>A primeira linha deve conter o cabeçalho.</li>
                        <li>Colunas obrigatórias: <code>matricula</code>, <code>codigo_evento</code>, <code>competencia</code>.</li>
                        <li>Colunas opcionais: <code>valor</code>, <code>dias</code>, <code>observacao</code>.</li>
                    </ul>
                </div>

                <form action="{{ route('lancamentos.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-4">
                        <label for="arquivo" class="form-label fw-bold">Selecione o arquivo CSV</label>
                        <input class="form-control form-control-lg" type="file" id="arquivo" name="arquivo" accept=".csv" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cloud-upload me-2"></i> Processar Importação
                        </button>
                        <a href="{{ route('lancamentos.index') }}" class="btn btn-outline-secondary">
                            Cancelar e Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
