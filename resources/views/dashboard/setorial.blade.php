@extends('layouts.app')

@section('title', 'Dashboard — Sistema de Frequência')
@section('description', 'Painel do setor com visão geral dos lançamentos')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold"><i class="bi bi-speedometer2 me-2"></i>Dashboard — {{ $setor->sigla ?? $setor->nome }}</h4>
    <p class="text-muted mb-0">Competência atual: <strong>{{ $competenciaAtual }}</strong></p>
</div>

{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107, #e0a800)">
            <div class="stat-number">{{ $contadores['pendentes'] }}</div>
            <div class="stat-label">Pendentes</div>
            <i class="bi bi-hourglass-split" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #198754, #157347)">
            <div class="stat-number">{{ $contadores['conferidos'] }}</div>
            <div class="stat-label">Conferidos</div>
            <i class="bi bi-check-circle" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545, #bb2d3b)">
            <div class="stat-number">{{ $contadores['rejeitados'] }}</div>
            <div class="stat-label">Rejeitados</div>
            <i class="bi bi-x-circle" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #6c757d, #5c636a)">
            <div class="stat-number">{{ $contadores['exportados'] }}</div>
            <div class="stat-label">Exportados</div>
            <i class="bi bi-download" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
</div>

{{-- Competência atual --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-semibold mb-3"><i class="bi bi-calendar3 me-1"></i> Competência {{ $competenciaAtual }}</h6>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted">Total de lançamentos</span>
                    <span class="fw-bold fs-5">{{ $contadoresMes['total'] }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted">Aguardando conferência</span>
                    <span class="fw-bold text-warning">{{ $contadoresMes['pendentes'] }}</span>
                </div>
                <div class="mt-3">
                    <a href="{{ route('lancamentos.create') }}" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i> Novo Lançamento
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-1"></i> Últimos Lançamentos</h6>
                @forelse ($ultimosLancamentos as $l)
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="fw-medium" style="font-size:0.85rem">{{ $l->servidor->nome }}</div>
                            <small class="text-muted">{{ $l->evento->descricao }}</small>
                        </div>
                        <div>
                            @if ($l->isPendente())
                                <span class="badge bg-warning text-dark">Pendente</span>
                            @elseif ($l->isConferido())
                                <span class="badge bg-success">Conferido</span>
                            @elseif ($l->isRejeitado())
                                <span class="badge bg-danger">Rejeitado</span>
                            @elseif ($l->isExportado())
                                <span class="badge bg-secondary">Exportado</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center mb-0">Sem lançamentos recentes</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Alertas de rejeitados --}}
@if ($rejeitadosRecentes->count() > 0)
<div class="card border-danger mb-4">
    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-1"></i> Lançamentos Rejeitados — Ação necessária</span>
        <span class="badge bg-white text-danger">{{ $rejeitadosRecentes->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Servidor</th>
                        <th>Evento</th>
                        <th>Motivo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rejeitadosRecentes as $r)
                        <tr>
                            <td>{{ $r->servidor->nome }}</td>
                            <td>{{ $r->evento->descricao }}</td>
                            <td class="text-danger" style="font-size:0.85rem">{{ Str::limit($r->motivo_rejeicao, 60) }}</td>
                            <td>
                                <a href="{{ route('lancamentos.edit', $r) }}" class="btn btn-sm btn-outline-primary" title="Corrigir e Reenviar">
                                    <i class="bi bi-arrow-repeat"></i> Corrigir
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
