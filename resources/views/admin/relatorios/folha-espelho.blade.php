@extends('layouts.app')

@section('title', 'Folha Espelho')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="text-primary fw-bold">Folha Espelho</h2>
        <p class="text-muted">Detalhamento de lançamentos por servidor.</p>
    </div>
    <div class="col-md-6">
        <form action="{{ route('admin.relatorios.folha-espelho') }}" method="GET" class="card shadow-sm border-0">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                <select name="competencia" class="form-select form-select-sm" style="max-width: 150px;">
                    <option value="">Selecione Comp.</option>
                    @foreach($competencias as $comp)
                        <option value="{{ $comp }}" {{ $competenciaAtual == $comp ? 'selected' : '' }}>
                            {{ $comp }}
                        </option>
                    @endforeach
                </select>
                
                <input type="text" name="busca" class="form-control form-select-sm" placeholder="Nome ou Matrícula..." value="{{ request('busca') }}">
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i>
                </button>
                
                <a href="{{ route('admin.relatorios.exportar-csv', array_merge(request()->all(), ['tipo' => 'folha_espelho'])) }}" class="btn btn-success btn-sm text-white text-nowrap">
                    <i class="bi bi-download"></i> CSV
                </a>
            </div>
        </form>
    </div>
</div>

@if(isset($dados) && count($dados) > 0)
    <div class="accordion shadow-sm" id="accordionServidores">
        @foreach($dados as $servidorId => $info)
            <div class="accordion-item border-0 mb-3 rounded overflow-hidden shadow-sm">
                <h2 class="accordion-header" id="heading{{ $servidorId }}">
                    <button class="accordion-button collapsed bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $servidorId }}" aria-expanded="false" aria-controls="collapse{{ $servidorId }}">
                        <div class="d-flex w-100 justify-content-between align-items-center me-3">
                            <div>
                                <div class="fw-bold">{{ $info['servidor']->nome }}</div>
                                <small class="text-muted">Matrícula: {{ $info['servidor']->matricula }} • {{ $info['servidor']->lotacao->first()->setor->nome ?? 'Sem lotação' }}</small>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-primary rounded-pill mb-1">{{ count($info['lancamentos']) }} evento(s)</div>
                                <div class="fw-bold text-success fs-6">R$ {{ number_format($info['total_valor'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="collapse{{ $servidorId }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $servidorId }}" data-bs-parent="#accordionServidores">
                    <div class="accordion-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Evento</th>
                                    <th class="text-center">Ref.</th>
                                    <th class="text-end pe-4">Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($info['lancamentos'] as $lancamento)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-secondary me-1">{{ $lancamento->evento->codigo_evento }}</span>
                                            {{ $lancamento->evento->descricao }}
                                        </td>
                                        <td class="text-center">
                                            @if($lancamento->dias_trabalhados) {{ $lancamento->dias_trabalhados }}D @endif
                                            @if($lancamento->porcentagem_insalubridade) {{ $lancamento->porcentagem_insalubridade }}% @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            R$ {{ number_format($lancamento->valor_total_calculado ?? $lancamento->valor, 2, ',', '.') }}
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: {{ $lancamento->status->cor() }}">
                                                {{ $lancamento->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    
    <div class="mt-4">
        {{ $dados->appends(request()->query())->links() }}
    </div>
@elseif(request('competencia'))
    <div class="alert alert-info d-flex align-items-center mb-0 shadow-sm border-0">
        <i class="bi bi-info-circle-fill me-2 fs-4"></i>
        <div>
            Nenhum lançamento encontrado para os filtros selecionados.
        </div>
    </div>
@else
    <div class="text-center py-5 text-muted">
        <i class="bi bi-search display-1 mb-3 opacity-25"></i>
        <h4>Selecione uma competência para visualizar</h4>
        <p>Utilize os filtros acima para gerar o relatório.</p>
    </div>
@endif
@endsection
