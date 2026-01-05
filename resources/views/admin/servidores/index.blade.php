@extends('layouts.app')

@section('title', 'Gerenciar Servidores')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-person-badge"></i> Gerenciar Servidores</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('admin.servidores.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Novo Servidor
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
                            <th>Matrícula</th>
                            <th>Nome</th>
                            <th>Setor</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($servidores as $servidor)
                            <tr>
                                <td>{{ $servidor->matricula }}</td>
                                <td>{{ $servidor->nome }}</td>
                                <td>{{ $servidor->setor->nome ?? 'N/A' }}</td>
                                <td>
                                    @if ($servidor->ativo)
                                        <span class="badge bg-success">Ativo</span>
                                    @else
                                        <span class="badge bg-danger">Inativo</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.servidores.edit', $servidor) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <form action="{{ route('admin.servidores.destroy', $servidor) }}" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza?')">
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
                                <td colspan="5" class="text-center text-muted py-4">Nenhum servidor encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($servidores->hasPages())
        <div class="mt-4">{{ $servidores->links() }}</div>
    @endif
</div>
@endsection
