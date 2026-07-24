@extends('layouts.app')

@section('title', 'Frequência Mensal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-clipboard2-check me-2"></i>Frequência Mensal</h1>
        <p class="text-muted mb-0">Preencha a frequência dos servidores diretamente no sistema para cada competência.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
@endif

@if ($competenciasAbertas->isNotEmpty())
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h2 class="h6 fw-bold">Abrir competência para preenchimento</h2>
            <form method="POST" action="{{ route('frequencia.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-lg-8">
                    <label for="competencia_id" class="form-label fw-semibold">Competência</label>
                    <select id="competencia_id" name="competencia_id" class="form-select" required>
                        <option value="">Selecione</option>
                        @foreach ($competenciasAbertas as $competencia)
                            <option value="{{ $competencia->id }}">{{ $competencia->descricao }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">A relação será formada com os servidores lotados no setor durante o período.</div>
                </div>
                <div class="col-lg-4 d-grid"><button class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Iniciar preenchimento</button></div>
            </form>
        </div>
    </div>
@endif

<div class="row g-3">
    @forelse ($folhas as $folha)
        @php $concluidos = $folha->servidores_count - $folha->pendentes_count; @endphp
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="h6 fw-bold mb-1">{{ $folha->competencia->descricao }}</h2>
                            <span class="badge text-bg-{{ $folha->status->cor() }}">{{ $folha->status->label() }}</span>
                        </div>
                        <a href="{{ route('frequencia.show', $folha) }}" class="btn btn-outline-primary btn-sm">Abrir</a>
                    </div>
                    <div class="mt-3" aria-label="{{ $concluidos }} de {{ $folha->servidores_count }} servidores preenchidos">
                        <div class="d-flex justify-content-between small mb-1"><span>Preenchimento</span><span>{{ $concluidos }}/{{ $folha->servidores_count }}</span></div>
                        @php $percentual = $folha->servidores_count > 0 ? round(($concluidos / $folha->servidores_count) * 100) : 0; @endphp
                        <div class="progress" role="progressbar" aria-valuenow="{{ $percentual }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $percentual }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Nenhuma competência iniciada para este setor.</div></div></div>
    @endforelse
</div>
@endsection
