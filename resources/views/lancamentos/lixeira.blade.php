@extends('layouts.app')

@section('title', 'Lixeira — Sistema de Frequência')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0 text-danger"><i class="bi bi-trash me-2"></i>Lixeira</h4>
        <p class="text-muted mb-0 small">Itens excluídos que podem ser restaurados.</p>
    </div>
    <a href="{{ route('lancamentos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Servidor</th>
                    <th>Evento</th>
                    <th>Competência</th>
                    <th>Data Exclusão</th>
                    <th class="text-end pe-4">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lancamentos as $lancamento)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold">{{ $lancamento->servidor->nome }}</div>
                            <div class="text-muted small">{{ $lancamento->servidor->matricula }}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-secondary me-2">{{ $lancamento->evento->codigo_evento }}</span>
                                <span>{{ $lancamento->evento->descricao }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ \Carbon\Carbon::createFromFormat('m/Y', $lancamento->competencia)->format('M/Y') }}
                            </span>
                        </td>
                        <td class="text-muted small">
                            {{ $lancamento->deleted_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-end pe-4">
                            <form action="{{ route('lancamentos.restaurar', $lancamento->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success text-white" title="Restaurar" onclick="return confirm('Restaurar este lançamento?')">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-trash display-1 opacity-25 mb-3"></i>
                            <div class="h5">Lixeira vazia</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($lancamentos->hasPages())
        <div class="card-footer bg-white pt-3 border-top-0">
            {{ $lancamentos->links() }}
        </div>
    @endif
</div>
@endsection
