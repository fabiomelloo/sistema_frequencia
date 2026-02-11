@extends('layouts.app')

@section('title', 'Resumo da Competência')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="text-primary fw-bold">Resumo da Competência</h2>
        <p class="text-muted">Visão geral dos lançamentos por setor e evento.</p>
    </div>
    <div class="col-md-6">
        <form action="{{ route('admin.relatorios.resumo') }}" method="GET" class="d-flex justify-content-end gap-2">
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-white"><i class="bi bi-calendar"></i></span>
                <select name="competencia" class="form-select" onchange="this.form.submit()">
                    @foreach($competencias as $comp)
                        <option value="{{ $comp }}" {{ $competenciaSelecionada == $comp ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::createFromFormat('m/Y', $comp)->format('M/Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.relatorios.exportar-csv', ['competencia' => $competenciaSelecionada, 'tipo' => 'resumo']) }}" class="btn btn-success text-white">
                <i class="bi bi-file-earmark-excel me-1"></i> Excel
            </a>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-primary text-white shadow-sm">
            <div class="stat-number">{{ $estatisticas['total_lancamentos'] }}</div>
            <div class="stat-label">Total de Lançamentos</div>
            <i class="bi bi-list-check position-absolute top-0 end-0 m-3 opacity-25 fs-1"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-success text-white shadow-sm">
            <div class="stat-number">R$ {{ number_format($estatisticas['total_valor'], 2, ',', '.') }}</div>
            <div class="stat-label">Valor Total</div>
            <i class="bi bi-currency-dollar position-absolute top-0 end-0 m-3 opacity-25 fs-1"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-warning text-dark shadow-sm">
            <div class="stat-number">{{ $estatisticas['total_pendentes'] }}</div>
            <div class="stat-label">Pendentes</div>
            <i class="bi bi-clock-history position-absolute top-0 end-0 m-3 opacity-25 fs-1"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-info text-dark shadow-sm">
            <div class="stat-number">{{ $estatisticas['total_setores'] }}</div>
            <div class="stat-label">Setores Ativos</div>
            <i class="bi bi-building position-absolute top-0 end-0 m-3 opacity-25 fs-1"></i>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">Lançamentos por Setor</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Setor</th>
                        <th class="text-center">Qtd. Lançamentos</th>
                        <th class="text-end pe-4">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porSetor as $item)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $item->setor_nome }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $item->qtd }}</span>
                            </td>
                            <td class="text-end pe-4 fw-bold text-success">
                                R$ {{ number_format($item->valor_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">Totais por Evento</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Evento</th>
                        <th class="text-center">Qtd. Ocorrências</th>
                        <th class="text-end pe-4">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porEvento as $item)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="badge bg-primary me-2">{{ $item->codigo_evento }}</div>
                                    <span>{{ $item->descricao }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $item->qtd }}</span>
                            </td>
                            <td class="text-end pe-4 fw-bold">
                                R$ {{ number_format($item->valor_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
