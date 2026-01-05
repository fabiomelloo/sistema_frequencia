@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Meus Lançamentos</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('lancamentos.create') }}" class="btn btn-primary">Novo Lançamento</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matrícula</th>
                        <th>Servidor</th>
                        <th>Evento</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lancamentos as $lancamento)
                        <tr>
                            <td>{{ $lancamento->servidor->matricula }}</td>
                            <td>{{ $lancamento->servidor->nome }}</td>
                            <td>{{ $lancamento->evento->descricao }}</td>
                            <td>
                                @if ($lancamento->isPendente())
                                    <span class="badge bg-warning text-dark">PENDENTE</span>
                                @elseif ($lancamento->isConferido())
                                    <span class="badge bg-success">CONFERIDO</span>
                                @elseif ($lancamento->isRejeitado())
                                    <span class="badge bg-danger">REJEITADO</span>
                                @elseif ($lancamento->isExportado())
                                    <span class="badge bg-secondary">EXPORTADO</span>
                                @endif
                            </td>
                            <td>{{ $lancamento->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('lancamentos.show', $lancamento) }}" class="btn btn-sm btn-info" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if ($lancamento->podeSerEditado())
                                    <a href="{{ route('lancamentos.edit', $lancamento) }}" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('lancamentos.destroy', $lancamento) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Deletar" onclick="return confirm('Tem certeza?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                Nenhum lançamento encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($lancamentos->hasPages())
        <nav aria-label="Page navigation" class="mt-4">
            {{ $lancamentos->links() }}
        </nav>
    @endif
</div>
@endsection
