@extends('layouts.app')

@section('title', 'Gerenciar Permissões')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-shield-check"></i> Gerenciar Permissões</h1>
            <p class="text-muted">Configure quais setores podem lançar quais eventos</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h5>Adicionar Nova Permissão</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.permissoes.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-5">
                        <label for="setor_id" class="form-label">Setor</label>
                        <select class="form-select" id="setor_id" name="setor_id" required>
                            <option value="">Selecione um setor...</option>
                            @foreach ($setores as $setor)
                                <option value="{{ $setor->id }}">{{ $setor->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="evento_id" class="form-label">Evento</label>
                        <select class="form-select" id="evento_id" name="evento_id" required>
                            <option value="">Selecione um evento...</option>
                            @foreach ($eventos as $evento)
                                <option value="{{ $evento->id }}">{{ $evento->codigo_evento }} - {{ $evento->descricao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Adicionar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Setor</th>
                            <th>Eventos Permitidos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($setores as $setor)
                            <tr>
                                <td>
                                    <strong>{{ $setor->nome }}</strong><br>
                                    <small class="text-muted">{{ $setor->sigla }}</small>
                                </td>
                                <td>
                                    @if ($setor->eventosPermitidos->count() > 0)
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($setor->eventosPermitidos as $evento)
                                                <span class="badge bg-primary">
                                                    {{ $evento->codigo_evento }} - {{ $evento->descricao }}
                                                    <form action="{{ route('admin.permissoes.destroy', ['setor' => $setor->id, 'evento' => $evento->id]) }}" 
                                                          method="POST" style="display:inline;" 
                                                          onsubmit="return confirm('Remover esta permissão?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="badge bg-danger border-0" style="cursor: pointer;">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">Nenhum evento permitido</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.setores.show', $setor) }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver Setor
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Nenhum setor encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
