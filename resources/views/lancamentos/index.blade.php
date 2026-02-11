@extends('layouts.app')

@section('title', 'Meus Lançamentos — Sistema de Frequência')
@section('description', 'Lista de lançamentos setoriais')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Meus Lançamentos</h4>
    <a href="{{ route('lancamentos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Novo Lançamento
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Filtros --}}
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('lancamentos.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Competência</label>
                <select name="competencia" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($competencias as $comp)
                        <option value="{{ $comp }}" @selected(($filtros['competencia'] ?? '') == $comp)>{{ $comp }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos (exceto exp.)</option>
                    <option value="PENDENTE" @selected(($filtros['status'] ?? '') == 'PENDENTE')>Pendente</option>
                    <option value="CONFERIDO" @selected(($filtros['status'] ?? '') == 'CONFERIDO')>Conferido</option>
                    <option value="REJEITADO" @selected(($filtros['status'] ?? '') == 'REJEITADO')>Rejeitado</option>
                    <option value="EXPORTADO" @selected(($filtros['status'] ?? '') == 'EXPORTADO')>Exportado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Servidor</label>
                <select name="servidor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($servidores as $s)
                        <option value="{{ $s->id }}" @selected(($filtros['servidor_id'] ?? '') == $s->id)>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Evento</label>
                <select name="evento_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($eventos as $e)
                        <option value="{{ $e->id }}" @selected(($filtros['evento_id'] ?? '') == $e->id)>{{ $e->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Busca</label>
                <input type="text" name="busca" class="form-control form-control-sm" placeholder="Matrícula ou nome" value="{{ $filtros['busca'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('lancamentos.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Matrícula</th>
                    <th>Servidor</th>
                    <th>Evento</th>
                    <th>Competência</th>
                    <th>Status</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lancamentos as $lancamento)
                    <tr>
                        <td><strong>{{ $lancamento->servidor->matricula }}</strong></td>
                        <td>{{ $lancamento->servidor->nome }}</td>
                        <td>{{ $lancamento->evento->descricao }}</td>
                        <td>{{ $lancamento->competencia }}</td>
                        <td>
                            @if ($lancamento->isPendente())
                                <span class="badge bg-warning text-dark">Pendente</span>
                            @elseif ($lancamento->isConferido())
                                <span class="badge bg-success">Conferido</span>
                            @elseif ($lancamento->isRejeitado())
                                <span class="badge bg-danger">Rejeitado</span>
                            @elseif ($lancamento->isExportado())
                                <span class="badge bg-secondary">Exportado</span>
                            @endif
                        </td>
                        <td>{{ $lancamento->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('lancamentos.show', $lancamento) }}" class="btn btn-sm btn-outline-info" title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if ($lancamento->podeSerEditado())
                                <a href="{{ route('lancamentos.edit', $lancamento) }}" class="btn btn-sm btn-outline-warning" title="{{ $lancamento->isRejeitado() ? 'Corrigir e Reenviar' : 'Editar' }}">
                                    <i class="bi bi-{{ $lancamento->isRejeitado() ? 'arrow-repeat' : 'pencil' }}"></i>
                                </a>
                                @if ($lancamento->isPendente())
                                    <form action="{{ route('lancamentos.destroy', $lancamento) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Deletar" onclick="return confirm('Tem certeza que deseja excluir este lançamento?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                            @if ($lancamento->isRejeitado() && $lancamento->motivo_rejeicao)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $lancamento->motivo_rejeicao }}">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem"></i>
                            <div class="mt-2">Nenhum lançamento encontrado.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($lancamentos->hasPages())
    <nav class="mt-3">{{ $lancamentos->links() }}</nav>
@endif
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(el){ new bootstrap.Tooltip(el); });
});
</script>
@endsection
