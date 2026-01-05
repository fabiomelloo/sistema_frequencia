@extends('layouts.app')

@section('title', 'Detalhes do Setor')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-building"></i> {{ $setor->nome }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.setores.edit', $setor) }}" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="{{ route('admin.setores.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informações do Setor</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nome:</strong> {{ $setor->nome }}</p>
                    <p><strong>Sigla:</strong> <span class="badge bg-secondary">{{ $setor->sigla }}</span></p>
                    <p><strong>Status:</strong> 
                        @if ($setor->ativo)
                            <span class="badge bg-success">Ativo</span>
                        @else
                            <span class="badge bg-danger">Inativo</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Estatísticas</h5>
                </div>
                <div class="card-body">
                    <p><strong>Usuários:</strong> {{ $setor->usuarios->count() }}</p>
                    <p><strong>Servidores:</strong> {{ $setor->servidores->count() }}</p>
                    <p><strong>Eventos Permitidos:</strong> {{ $eventosPermitidos->count() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
