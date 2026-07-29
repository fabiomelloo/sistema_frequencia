@extends('layouts.app')

@section('title', 'Ocorrências de Frequência')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar2-week me-2"></i>Ocorrências de Frequência</h1>
        <p class="text-muted mb-0">Fatos registrados a partir dos documentos setoriais, sem efeito financeiro automático.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('ocorrencias.create') }}" class="btn btn-primary rounded-pill px-3 shadow-sm">
            <i class="bi bi-plus-circle me-1"></i>Nova Ocorrência
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="status">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('ocorrencias.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="competencia_id" class="form-label small fw-semibold">Competência</label>
                <select id="competencia_id" name="competencia_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($competencias as $competencia)
                        <option value="{{ $competencia->id }}" @selected(($filtros['competencia_id'] ?? null) == $competencia->id)>{{ $competencia->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="servidor_id" class="form-label small fw-semibold">Servidor</label>
                <select id="servidor_id" name="servidor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($servidores as $servidor)
                        <option value="{{ $servidor->id }}" @selected(($filtros['servidor_id'] ?? null) == $servidor->id)>{{ $servidor->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label small fw-semibold">Tipo</label>
                <select id="tipo" name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(($filtros['tipo'] ?? null) === $tipo->value)>{{ $tipo->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-outline-primary btn-sm" aria-label="Aplicar filtros"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Servidor</th>
                    <th>Competência</th>
                    <th>Tipo</th>
                    <th>Período/dias</th>
                    <th>Justificação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ocorrencias as $ocorrencia)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $ocorrencia->servidor->nome }}</div>
                            <small class="text-muted">{{ $ocorrencia->servidor->matricula }}</small>
                        </td>
                        <td>{{ $ocorrencia->competencia->descricao }}</td>
                        <td>{{ $ocorrencia->tipo->label() }}</td>
                        <td>
                            @if ($ocorrencia->data_inicio)
                                {{ $ocorrencia->data_inicio->format('d/m/Y') }} a {{ $ocorrencia->data_fim->format('d/m/Y') }}
                            @elseif ($ocorrencia->dias->isNotEmpty())
                                {{ $ocorrencia->dias->pluck('data')->map->format('d/m/Y')->join(', ') }}
                            @endif
                        </td>
                        <td>
                            @if (is_null($ocorrencia->justificada))
                                <span class="text-muted">Não informada</span>
                            @else
                                <span class="badge text-bg-{{ $ocorrencia->justificada ? 'success' : 'warning' }}">{{ $ocorrencia->justificada ? 'Justificada' : 'Não justificada' }}</span>
                            @endif
                        </td>
                        <td class="text-end"><a href="{{ route('ocorrencias.show', $ocorrencia) }}" class="btn btn-outline-primary btn-sm">Detalhes</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">Nenhuma ocorrência encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($ocorrencias->hasPages())
        <div class="card-footer bg-white">{{ $ocorrencias->links() }}</div>
    @endif
</div>
@endsection
