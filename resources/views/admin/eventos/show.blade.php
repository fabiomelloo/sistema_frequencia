@extends('layouts.app')

@section('title', 'Detalhes do Evento')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-event"></i> {{ $evento->descricao }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.eventos.edit', $evento) }}" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="{{ route('admin.eventos.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informações do Evento</h5>
                </div>
                <div class="card-body">
                    <p><strong>Código:</strong> {{ $evento->codigo_evento }}</p>
                    <p><strong>Descrição:</strong> {{ $evento->descricao }}</p>
                    <p><strong>Status:</strong> 
                        @if ($evento->ativo)
                            <span class="badge bg-success">Ativo</span>
                        @else
                            <span class="badge bg-danger">Inativo</span>
                        @endif
                    </p>
                    <p><strong>Exige Dias:</strong> {{ $evento->exige_dias ? 'Sim' : 'Não' }}</p>
                    <p><strong>Exige Valor:</strong> {{ $evento->exige_valor ? 'Sim' : 'Não' }}</p>
                    <p><strong>Exige Observação:</strong> {{ $evento->exige_observacao ? 'Sim' : 'Não' }}</p>
                    <p><strong>Exige Porcentagem:</strong> {{ $evento->exige_porcentagem ? 'Sim' : 'Não' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Limites e Valores</h5>
                </div>
                <div class="card-body">
                    <p><strong>Valor Mínimo:</strong> {{ $evento->valor_minimo ? 'R$ ' . number_format($evento->valor_minimo, 2, ',', '.') : 'N/A' }}</p>
                    <p><strong>Valor Máximo:</strong> {{ $evento->valor_maximo ? 'R$ ' . number_format($evento->valor_maximo, 2, ',', '.') : 'N/A' }}</p>
                    <p><strong>Dias Máximo:</strong> {{ $evento->dias_maximo ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Setores com Permissão</h5>
                </div>
                <div class="card-body">
                    @if ($evento->setoresComDireito->count() > 0)
                        <ul class="list-group">
                            @foreach ($evento->setoresComDireito as $setor)
                                <li class="list-group-item">{{ $setor->nome }} ({{ $setor->sigla }})</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">Nenhum setor tem permissão para este evento.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
