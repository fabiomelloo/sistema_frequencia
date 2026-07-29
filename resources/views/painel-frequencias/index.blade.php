@extends('layouts.app')

@section('title', 'Conferência de Frequências')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar2-check me-2"></i>Conferência de Frequências</h1>
        <p class="text-muted mb-0">Acompanhe o envio dos setores e confira cada competência antes da aprovação.</p>
    </div>
</div>

@if (session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

<div class="row g-2 mb-4">
    @foreach ($statusDisponiveis as $status)
        <div class="col-6 col-xl-3">
            <a href="{{ route('painel-frequencias.index', ['status' => $status->value] + $filtros) }}" class="card border-{{ $statusAtual === $status ? $status->cor() : '0' }} shadow-sm text-decoration-none h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">{{ $status->label() }}</div>
                    <div class="h4 text-{{ $status->cor() }} mb-0">{{ $contadores[$status->value] ?? 0 }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('painel-frequencias.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="status" value="{{ $statusAtual->value }}">
            <div class="col-md-5">
                <label for="competencia_id" class="form-label small fw-semibold">Competência</label>
                <select id="competencia_id" name="competencia_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($competencias as $competencia)<option value="{{ $competencia->id }}" @selected(($filtros['competencia_id'] ?? '') == $competencia->id)>{{ $competencia->descricao }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label for="setor_id" class="form-label small fw-semibold">Setor</label>
                <select id="setor_id" name="setor_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($setores as $setor)<option value="{{ $setor->id }}" @selected(($filtros['setor_id'] ?? '') == $setor->id)>{{ $setor->nome }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button></div>
        </form>
    </div>
</div>

<div class="d-flex flex-column gap-3">
    @forelse ($folhas as $folha)
        <article class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <h2 class="h6 fw-bold mb-1">{{ $folha->setor->nome }}</h2>
                        <div class="text-muted small">{{ $folha->competencia->descricao }}</div>
                    </div>
                    <div class="col-6 col-lg-2"><span class="badge text-bg-{{ $folha->status->cor() }}">{{ $folha->status->label() }}</span></div>
                    <div class="col-6 col-lg-2 small"><strong>{{ $folha->servidores_count }}</strong> servidores</div>
                    <div class="col-lg-3 d-flex justify-content-lg-end">
                        <a href="{{ route('painel-frequencias.show', $folha) }}" class="btn btn-primary btn-sm"><i class="bi bi-eye me-1"></i>Conferir detalhes</a>
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm"><div class="card-body py-5 text-center text-muted">Nenhuma frequência encontrada para os filtros selecionados.</div></div>
    @endforelse
</div>

<div class="mt-4">{{ $folhas->links() }}</div>
@endsection
