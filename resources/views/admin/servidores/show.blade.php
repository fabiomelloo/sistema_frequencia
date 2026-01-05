@extends('layouts.app')

@section('title', 'Detalhes do Servidor')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-person-badge"></i> {{ $servidor->nome }}</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.servidores.edit', $servidor) }}" class="btn btn-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="{{ route('admin.servidores.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informações do Servidor</h5>
                </div>
                <div class="card-body">
                    <p><strong>Matrícula:</strong> {{ $servidor->matricula }}</p>
                    <p><strong>Nome:</strong> {{ $servidor->nome }}</p>
                    <p><strong>Setor:</strong> {{ $servidor->setor->nome ?? 'N/A' }}</p>
                    <p><strong>Origem:</strong> {{ $servidor->origem_registro ?? 'N/A' }}</p>
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
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Lançamentos</h5>
                </div>
                <div class="card-body">
                    <p><strong>Total de Lançamentos:</strong> {{ $servidor->lancamentos->count() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
