@extends('layouts.app')

@section('title', 'Dashboard Central — Sistema de Frequência')
@section('description', 'Painel administrativo com visão geral do sistema')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold"><i class="bi bi-speedometer2 me-2"></i>Dashboard Central</h4>
    <p class="text-muted mb-0">Competência atual: <strong>{{ $competenciaAtual }}</strong></p>
</div>

{{-- KPIs globais --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107, #e0a800)">
            <div class="stat-number">{{ $contadores['pendentes'] }}</div>
            <div class="stat-label">Pendentes</div>
            <i class="bi bi-hourglass-split" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #198754, #157347)">
            <div class="stat-number">{{ $contadores['conferidos'] }}</div>
            <div class="stat-label">Conferidos</div>
            <i class="bi bi-check-circle" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545, #bb2d3b)">
            <div class="stat-number">{{ $contadores['rejeitados'] }}</div>
            <div class="stat-label">Rejeitados</div>
            <i class="bi bi-x-circle" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #6c757d, #5c636a)">
            <div class="stat-number">{{ $contadores['exportados'] }}</div>
            <div class="stat-label">Exportados</div>
            <i class="bi bi-download" style="position:absolute;right:12px;bottom:10px;font-size:1.8rem;opacity:0.2"></i>
        </div>
    </div>
</div>

{{-- Resumo do sistema --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center p-3">
            <div class="text-primary"><i class="bi bi-people" style="font-size:2rem"></i></div>
            <div class="fw-bold fs-4">{{ $totalServidores }}</div>
            <small class="text-muted">Servidores Ativos</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-3">
            <div class="text-success"><i class="bi bi-building" style="font-size:2rem"></i></div>
            <div class="fw-bold fs-4">{{ $totalSetores }}</div>
            <small class="text-muted">Setores Ativos</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-3">
            <div class="text-info"><i class="bi bi-calendar-event" style="font-size:2rem"></i></div>
            <div class="fw-bold fs-4">{{ $totalEventos }}</div>
            <small class="text-muted">Eventos Ativos</small>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Pendentes por setor --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-1"></i> Pendentes por Setor</h6>
            </div>
            <div class="card-body">
                @if ($pendentesPorSetor->count() > 0)
                    <canvas id="chartPendentesPorSetor" height="200"></canvas>
                @else
                    <p class="text-muted text-center py-4">Nenhum lançamento pendente</p>
                @endif
            </div>
        </div>
    </div>

    {{-- SLA: Pendentes mais antigos --}}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-1"></i> Pendentes mais antigos</h6>
                <a href="{{ route('painel.index', ['status' => 'PENDENTE']) }}" class="btn btn-outline-primary btn-sm">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:0.85rem">
                        <thead class="table-light">
                            <tr>
                                <th>Servidor</th>
                                <th>Setor</th>
                                <th>Dias</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendentesAntigos as $p)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $p->servidor->nome }}</div>
                                        <small class="text-muted">{{ $p->evento->descricao }}</small>
                                    </td>
                                    <td>{{ $p->setorOrigem->sigla ?? $p->setorOrigem->nome }}</td>
                                    <td>
                                        @php $dias = $p->created_at->diffInDays(now()); @endphp
                                        <span class="badge {{ $dias > 7 ? 'bg-danger' : ($dias > 3 ? 'bg-warning text-dark' : 'bg-info') }}">
                                            {{ $dias }}d
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-3 text-muted">Nenhum pendente</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if ($pendentesPorSetor->count() > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartPendentesPorSetor').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($pendentesPorSetor->pluck('setor')),
            datasets: [{
                label: 'Pendentes',
                data: @json($pendentesPorSetor->pluck('total')),
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                borderColor: '#ffc107',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
});
</script>
@endif
@endsection
