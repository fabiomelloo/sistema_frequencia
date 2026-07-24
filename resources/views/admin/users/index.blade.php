@extends('layouts.app')

@section('title', 'Gerenciar Usuários')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-people"></i> Gerenciar Usuários</h1>
        </div>
        <div class="col-md-4 text-end">
            @can('create', \App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Novo Usuário
            </a>
            @endcan
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
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Setor</th>
                            <th>Função</th>
                            <th>Situação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->setor->nome ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-{{ $user->role->color() }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $user->ativo ? 'success' : 'secondary' }}">
                                        {{ $user->ativo ? 'Ativo' : 'Desativado' }}
                                    </span>
                                    @if ($user->estaBloqueado())
                                        <span class="badge text-bg-warning">Bloqueado temporariamente</span>
                                    @endif
                                </td>
                                <td>
                                    @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-warning" aria-label="Editar usuário">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                                    </a>
                                    @endcan
                                    @if ($user->ativo)
                                        @can('delete', $user)
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Desativar este usuário e encerrar suas sessões?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Desativar usuário">
                                                <i class="bi bi-person-x" aria-hidden="true"></i> Desativar
                                            </button>
                                        </form>
                                        @endcan
                                    @else
                                        @can('update', $user)
                                        <form action="{{ route('admin.users.ativar', $user) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success" aria-label="Reativar usuário">
                                                <i class="bi bi-person-check" aria-hidden="true"></i> Reativar
                                            </button>
                                        </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
