@extends('layouts.app')

@section('title', 'Detalhes do Item Mensal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('admin.eventos.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar ao catálogo</a>
        <h1 class="h4 fw-bold mt-2 mb-1">{{ $evento->descricao }}</h1>
        <p class="text-muted mb-0">{{ $evento->sigla ?: $evento->codigo_evento }} · código oficial {{ $evento->codigo_evento }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-{{ $evento->regra_validada ? 'success' : 'warning' }} fs-6 align-self-center"><i class="bi bi-{{ $evento->regra_validada ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $evento->regra_validada ? 'Regra validada' : 'Revisão pendente' }}</span>
        @can('update', $evento)<a href="{{ route('admin.eventos.edit', $evento) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar regra</a>@endcan
    </div>
</div>

@if (! $evento->regra_validada)
    <div class="alert alert-warning" role="status"><strong>Este item ainda precisa de revisão.</strong> A classificação atual preserva o comportamento anterior, mas ainda não representa uma regra administrativa confirmada.</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Regra mensal</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-secondary">Grupo</dt><dd class="col-sm-8">{{ $evento->tipo_evento?->label() ?? 'Não definido' }}</dd>
                    <dt class="col-sm-4 text-secondary">Forma</dt><dd class="col-sm-8">{{ $evento->unidade_lancamento?->label() ?? 'Não definida' }}</dd>
                    <dt class="col-sm-4 text-secondary">Origem</dt><dd class="col-sm-8">{{ $evento->origem_informacao?->label() ?? 'Não definida' }}</dd>
                    <dt class="col-sm-4 text-secondary">Efeito financeiro</dt><dd class="col-sm-8">{{ $evento->gera_efeito_financeiro ? 'Sim' : 'Não, apenas informativo' }}</dd>
                    <dt class="col-sm-4 text-secondary">Documento</dt><dd class="col-sm-8">{{ $evento->exige_documento ? 'Obrigatório' : 'Não obrigatório' }}</dd>
                    <dt class="col-sm-4 text-secondary">Disponibilidade</dt><dd class="col-sm-8">{{ $evento->ativo ? 'Disponível' : 'Inativo' }}</dd>
                </dl>
                <hr>
                <h3 class="small fw-bold">Instrução oficial</h3>
                <p class="mb-0 text-muted">{{ $evento->instrucoes_lancamento ?: 'Ainda não documentada.' }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3">Exigências e limites</h2>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Quantidade de dias</span><strong>{{ $evento->exige_dias ? 'Sim' : 'Não' }}</strong></li>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Valor monetário</span><strong>{{ $evento->exige_valor ? 'Sim' : 'Não' }}</strong></li>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Percentual</span><strong>{{ $evento->exige_porcentagem ? 'Sim' : 'Não' }}</strong></li>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Observação</span><strong>{{ $evento->exige_observacao ? 'Sim' : 'Não' }}</strong></li>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Limite de dias</span><strong>{{ $evento->dias_maximo ?? 'Não definido' }}</strong></li>
                    <li class="list-group-item px-0 d-flex justify-content-between"><span>Faixa de valor</span><strong>@if ($evento->valor_minimo !== null && $evento->valor_maximo !== null)R$ {{ number_format($evento->valor_minimo, 2, ',', '.') }} a R$ {{ number_format($evento->valor_maximo, 2, ',', '.') }}@else Não definida @endif</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-body p-4">
        <h2 class="h6 fw-bold">Setores autorizados</h2>
        @if ($evento->setoresComDireito->isNotEmpty())
            <div class="d-flex flex-wrap gap-2">@foreach ($evento->setoresComDireito as $setor)<span class="badge text-bg-light border text-dark">{{ $setor->nome }}</span>@endforeach</div>
        @else
            <p class="small text-muted mb-0">Nenhum setor está autorizado a utilizar este item.</p>
        @endif
    </div>
</div>
@endsection
