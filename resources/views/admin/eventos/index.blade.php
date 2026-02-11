@extends('layouts.app')

@section('title', 'Gerenciar Eventos')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-event"></i> Gerenciar Eventos</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.eventos.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Novo Evento
            </a>
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($eventos as $evento)
                            <tr>
                                <td><strong>{{ $evento->codigo_evento }}</strong></td>
                                <td>{{ $evento->descricao }}</td>
                                <td>
                                    @if($evento->tipo_evento)
                                        <span class="badge bg-info text-dark">{{ $evento->tipo_evento->label() }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($evento->ativo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-danger">Inativo</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.eventos.show', $evento) }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="{{ route('admin.eventos.edit', $evento) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <form action="{{ route('admin.eventos.destroy', $evento) }}" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Deletar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhum evento encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($eventos->hasPages())
        <div class="mt-4">{{ $eventos->links() }}</div>
    @endif
</div>
@endsection
