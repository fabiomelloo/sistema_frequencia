@extends('layouts.app')

@section('title', 'Frequência — '.$folha->competencia->referencia)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('frequencia.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar às competências</a>
        <h1 class="h4 fw-bold mt-2 mb-1">Frequência de {{ $folha->competencia->descricao }}</h1>
        <p class="text-muted mb-0">{{ $folha->setor->nome }}</p>
    </div>
    <span class="badge text-bg-{{ $folha->status->cor() }} fs-6">{{ $folha->status->label() }}</span>
</div>

@if (session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

@if ($folha->estaDevolvida())
    <div class="alert alert-danger" role="alert">
        <div class="fw-bold"><i class="bi bi-arrow-return-left me-1"></i>A Central devolveu esta frequência para correção</div>
        <div class="mt-1">{{ $folha->motivo_devolucao }}</div>
        @if ($folha->conferidoPor)<div class="small mt-2">Por {{ $folha->conferidoPor->name }} em {{ $folha->conferida_em?->format('d/m/Y H:i') }}.</div>@endif
    </div>
@elseif ($folha->estaAprovada())
    <div class="alert alert-success" role="status">
        <i class="bi bi-check-circle me-1"></i>Frequência aprovada pela Central
        @if ($folha->conferidoPor)
            , por {{ $folha->conferidoPor->name }} em {{ $folha->conferida_em?->format('d/m/Y H:i') }}
        @endif
        .
    </div>
@elseif ($folha->estaFinalizada())
    <div class="alert alert-info" role="status"><i class="bi bi-hourglass-split me-1"></i>Frequência enviada e aguardando conferência da Central.</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Servidores</small><div class="h4 mb-0">{{ $resumo['total'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Pendentes</small><div class="h4 mb-0 text-secondary">{{ $resumo['pendentes'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Frequência integral</small><div class="h4 mb-0 text-success">{{ $resumo['integrais'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Com faltas</small><div class="h4 mb-0 text-warning">{{ $resumo['com_faltas'] }}</div></div></div></div>
</div>

<div class="alert alert-info" role="note">
    Marque <strong>Frequência integral</strong> quando não houver falta. Para <strong>Com faltas</strong>, registre primeiro os dias na ocorrência do servidor. Férias, licenças, viagens e outros fatos também são registrados como ocorrências.
</div>

<div class="d-flex flex-column gap-3">
    @forelse ($folha->servidores as $item)
        @php
            $ocorrencias = $ocorrenciasPorServidor->get($item->servidor_id, collect());
            $conferenciaServidor = $item->conferenciaNaRodada($folha->rodada_conferencia);
        @endphp
        <article class="card shadow-sm border-0" id="servidor-{{ $item->id }}">
            <div class="card-body p-4">
                @if ($folha->estaDevolvida() && $conferenciaServidor?->status === \App\Enums\ConferenciaServidorStatus::DIVERGENTE)
                    <div class="alert alert-danger mb-3" role="alert">
                        <div class="fw-semibold"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Divergência apontada pela Central</div>
                        <div>{{ $conferenciaServidor->apontamento }}</div>
                        <div class="small mt-1">Por {{ $conferenciaServidor->conferidoPor?->name ?? 'Central' }} em {{ $conferenciaServidor->conferido_em->format('d/m/Y H:i') }}.</div>
                    </div>
                @endif
                <div class="row g-3 align-items-start">
                    <div class="col-lg-4">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <h2 class="h6 fw-bold mb-1">{{ $item->nome }}</h2>
                                <div class="text-muted small">Matrícula {{ $item->matricula }}</div>
                            </div>
                            <span class="badge text-bg-{{ $item->status->cor() }}">{{ $item->status->sigla() }} · {{ $item->status->label() }}</span>
                        </div>
                        <dl class="row small mt-3 mb-0">
                            <dt class="col-4 text-secondary">Cargo</dt><dd class="col-8">{{ $item->cargo ?: 'Não informado' }}</dd>
                            <dt class="col-4 text-secondary">Vínculo</dt><dd class="col-8">{{ $item->vinculo ?: 'Não informado' }}</dd>
                            <dt class="col-4 text-secondary">Carga</dt><dd class="col-8">{{ $item->carga_horaria ? $item->carga_horaria.'h' : 'Não informada' }}</dd>
                            @if (! empty($item->designacoes_snapshot))
                                <dt class="col-4 text-secondary">Função</dt><dd class="col-8">{{ collect($item->designacoes_snapshot)->map(fn ($d) => $d['tipo'].(!empty($d['nivel']) ? '-'.$d['nivel'] : ''))->join(', ') }}</dd>
                            @endif
                        </dl>
                        @if ($item->mudanca_funcional_no_periodo)
                            <div class="alert alert-warning small py-2 mt-2 mb-0" role="note"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Houve mudança funcional dentro do período; a Central deverá conferir a vigência.</div>
                        @endif
                    </div>

                    <div class="col-lg-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold">Ocorrências: {{ $ocorrencias->count() }}</span>
                            @can('update', $folha)
                                <a href="{{ route('ocorrencias.create', ['servidor_id' => $item->servidor_id, 'competencia_id' => $folha->competencia_id, 'retorno' => route('frequencia.show', $folha).'#servidor-'.$item->id]) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-plus-circle me-1"></i>Registrar
                                </a>
                            @endcan
                        </div>
                        @forelse ($ocorrencias as $ocorrencia)
                            <a href="{{ route('ocorrencias.show', $ocorrencia) }}" class="d-block small text-decoration-none mb-1">
                                {{ $ocorrencia->tipo->label() }}
                                @if ($ocorrencia->dias->isNotEmpty()) · {{ $ocorrencia->dias->pluck('data')->map->format('d/m')->join(', ') }} @endif
                            </a>
                        @empty
                            <span class="small text-muted">Nenhuma ocorrência registrada.</span>
                        @endforelse
                    </div>

                    <div class="col-lg-5">
                        @can('update', $folha)
                            <form method="POST" action="{{ route('frequencia.servidores.update', [$folha, $item]) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label for="status-{{ $item->id }}" class="form-label small fw-semibold">Situação</label>
                                        <select id="status-{{ $item->id }}" name="status" class="form-select form-select-sm" required>
                                            @foreach ($statusFrequencia as $status)
                                                <option value="{{ $status->value }}" @selected($item->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label for="observacao-{{ $item->id }}" class="form-label small fw-semibold">Observação geral</label>
                                        <input id="observacao-{{ $item->id }}" name="observacao_geral" value="{{ $item->observacao_geral }}" maxlength="1000" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>Salvar servidor</button></div>
                                </div>
                            </form>
                        @else
                            <div class="small text-muted">{{ $item->observacao_geral ?: 'Sem observação geral.' }}</div>
                        @endcan
                    </div>
                </div>
                @include('frequencia._itens-servidor', ['item' => $item, 'folha' => $folha, 'eventosMensais' => $eventosMensais])
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Nenhum servidor elegível foi encontrado para esta competência.</div></div>
    @endforelse
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h6 fw-bold mb-1">Envio para conferência</h2>
            <p class="small text-muted mb-0">Ao enviar, a edição fica bloqueada enquanto a Central realiza a conferência.</p>
        </div>
        @if ($folha->estaFinalizada())
            @can('reabrir', $folha)
                <form method="POST" action="{{ route('frequencia.reabrir', $folha) }}">@csrf<button class="btn btn-outline-warning"><i class="bi bi-unlock me-1"></i>Cancelar envio e corrigir</button></form>
            @endcan
        @else
            @can('finalizar', $folha)
                <form method="POST" action="{{ route('frequencia.finalizar', $folha) }}" onsubmit="return confirm('Enviar esta frequência para conferência da Central?');">@csrf<button class="btn btn-primary" @disabled($resumo['pendentes'] > 0 || $resumo['total'] === 0)><i class="bi bi-send me-1"></i>Enviar para a Central</button></form>
            @endcan
        @endif
    </div>
</div>
@endsection
