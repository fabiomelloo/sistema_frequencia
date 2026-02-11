@extends('layouts.app')

@section('title', 'Auditoria — Sistema de Frequência')
@section('description', 'Registros de auditoria do sistema')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Auditoria do Sistema</h4>
</div>

{{-- Filtros --}}
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Ação</label>
                <select name="acao" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($acoes as $a)
                        <option value="{{ $a }}" @selected(($filtros['acao'] ?? '') == $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Modelo</label>
                <select name="modelo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($modelos as $m)
                        <option value="{{ $m }}" @selected(($filtros['modelo'] ?? '') == $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Data Início</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ $filtros['data_inicio'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Data Fim</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="{{ $filtros['data_fim'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:0.85rem">
            <thead class="table-light">
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Modelo</th>
                    <th>Descrição</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->user_name ?? 'Sistema' }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'bg-success' => in_array($log->acao, ['CRIOU', 'APROVOU', 'LOGIN']),
                                'bg-warning text-dark' => $log->acao === 'EDITOU',
                                'bg-danger' => in_array($log->acao, ['EXCLUIU', 'REJEITOU']),
                                'bg-info' => $log->acao === 'EXPORTOU',
                                'bg-secondary' => $log->acao === 'LOGOUT',
                            ])>{{ $log->acao }}</span>
                        </td>
                        <td>{{ $log->modelo }} #{{ $log->modelo_id }}</td>
                        <td>{{ Str::limit($log->descricao, 80) }}</td>
                        <td><code>{{ $log->ip }}</code></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-journal" style="font-size:2rem"></i>
                            <div class="mt-2">Nenhum registro de auditoria encontrado.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($logs->hasPages())
    <nav class="mt-3">{{ $logs->links() }}</nav>
@endif
@endsection
