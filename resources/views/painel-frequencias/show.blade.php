@extends('layouts.app')

@section('title', 'Conferir Frequência')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <a href="{{ route('painel-frequencias.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Voltar à conferência</a>
        <h1 class="h4 fw-bold mt-2 mb-1">{{ $folha->setor->nome }}</h1>
        <p class="text-muted mb-0">{{ $folha->competencia->descricao }} · Rodada {{ $folha->rodada_conferencia }}</p>
    </div>
    <span class="badge text-bg-{{ $folha->status->cor() }} fs-6">{{ $folha->status->label() }}</span>
</div>

@if (session('success'))
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
@endif

@if ($folha->estaDevolvida())
    <div class="alert alert-danger" role="status"><strong>Orientação enviada ao setor:</strong><br>{{ $folha->motivo_devolucao }}</div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body row g-3">
        <div class="col-md-4"><span class="text-muted d-block">Enviada por</span><strong>{{ $folha->finalizadoPor?->name ?? 'Não informado' }}</strong></div>
        <div class="col-md-4"><span class="text-muted d-block">Data do envio</span><strong>{{ $folha->finalizada_em?->format('d/m/Y H:i') ?? 'Não enviada' }}</strong></div>
        <div class="col-md-4"><span class="text-muted d-block">Servidores</span><strong>{{ $folha->servidores->count() }}</strong></div>
    </div>
</div>

<div class="row g-3 mb-4" aria-label="Resumo da conferência">
    <div class="col-6 col-lg-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted d-block">Total</span><strong class="h4">{{ $resumoConferencia['total'] }}</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted d-block">Pendentes</span><strong class="h4 text-secondary">{{ $resumoConferencia['pendentes'] }}</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted d-block">Conferidos</span><strong class="h4 text-success">{{ $resumoConferencia['conferidos'] }}</strong></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted d-block">Divergentes</span><strong class="h4 text-danger">{{ $resumoConferencia['divergentes'] }}</strong></div></div></div>
</div>

@if ($folha->estaFinalizada())
    <div class="alert alert-info" role="note">
        Confira cada servidor. A aprovação ficará disponível somente quando todas as linhas estiverem marcadas como <strong>Conferido</strong>. Para devolver, registre ao menos uma divergência.
    </div>
@endif

<div class="d-flex flex-column gap-3">
    @foreach ($folha->servidores as $item)
        @php
            $itensOcorrencia = $ocorrencias->get($item->servidor_id, collect());
            $conferencia = $conferenciasAtuais->get($item->id);
            $errosConferencia = $errors->getBag('conferencia_'.$item->id);
            $formComErro = $errosConferencia->any();
            $corConferencia = $conferencia?->status->cor() ?? 'secondary';
            $rotuloConferencia = $conferencia?->status->label() ?? 'Pendente de conferência';
        @endphp
        <article class="card shadow-sm border-{{ $corConferencia }}" id="servidor-{{ $item->id }}">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-1">{{ $item->nome }}</h2>
                        <div class="text-muted">Matrícula {{ $item->matricula }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <span class="badge text-bg-{{ $item->status->cor() }}">{{ $item->status->sigla() }} · {{ $item->status->label() }}</span>
                        <span class="badge text-bg-{{ $corConferencia }}">{{ $rotuloConferencia }}</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="fw-semibold mb-2">Situação funcional</div>
                        <div>{{ $item->cargo ?: 'Cargo não informado' }}{{ $item->carga_horaria ? ' · '.$item->carga_horaria.'h' : '' }}</div>
                        <div class="text-muted">Vínculo: {{ $item->vinculo ?: 'não informado' }}</div>
                        @if (! empty($item->designacoes_snapshot))
                            <div class="text-muted mt-1">Função: {{ collect($item->designacoes_snapshot)->map(fn ($designacao) => $designacao['tipo'].(! empty($designacao['nivel']) ? '-'.$designacao['nivel'] : ''))->join(', ') }}</div>
                        @endif
                        @if ($item->mudanca_funcional_no_periodo)
                            <div class="alert alert-warning py-2 mt-2 mb-0" role="note"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Mudança funcional dentro do período.</div>
                        @endif
                    </div>
                    <div class="col-lg-5">
                        <div class="fw-semibold mb-2">Ocorrências registradas</div>
                        @forelse ($itensOcorrencia as $ocorrencia)
                            <div class="border rounded p-2 mb-2">
                                <div><strong>{{ $ocorrencia->tipo->label() }}</strong>@if ($ocorrencia->dias->isNotEmpty()) · {{ $ocorrencia->dias->pluck('data')->map->format('d/m/Y')->join(', ') }}@endif</div>
                                @if ($ocorrencia->possui_comprovacao || $ocorrencia->evidencias->isNotEmpty())
                                    @if ($ocorrencia->possui_comprovacao)<div class="small text-muted mb-2">Comprovação informada pelo setor.</div>@endif
                                    @include('evidencias._lista', [
                                        'evidencias' => $ocorrencia->evidencias,
                                        'contextoRevisao' => true,
                                        'folha' => $folha,
                                        'item' => $item,
                                    ])
                                @endif
                            </div>
                        @empty
                            <span class="text-muted">Nenhuma ocorrência.</span>
                        @endforelse
                    </div>
                    <div class="col-lg-3"><div class="fw-semibold mb-2">Observação do setor</div><div class="text-muted">{{ $item->observacao_geral ?: 'Sem observação.' }}</div></div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <h3 class="h6 fw-semibold mb-0">Itens da competência</h3>
                        <span class="badge text-bg-light border">{{ $item->itens->count() }} item(ns)</span>
                    </div>
                    <div class="row g-2">
                        @forelse ($item->itens as $registro)
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="fw-semibold">{{ $registro->sigla ?: $registro->codigo_evento }} · {{ $registro->descricao }}</div>
                                    <div>{{ $registro->conteudo() }}</div>
                                    <div class="text-muted">{{ $registro->editavel_pelo_setor ? 'Informado pelo setor' : 'Cadastro funcional' }}</div>
                                    @if ($registro->referencia_documento)<div class="text-muted">Documento: {{ $registro->referencia_documento }}</div>@endif
                                    @if ($registro->observacao)<div class="text-muted">{{ $registro->observacao }}</div>@endif
                                    @if ($registro->exige_documento || $registro->evidencias->isNotEmpty())
                                    <div class="border-top mt-2 pt-2">
                                        <div class="small fw-semibold mb-2">Documentos{{ $registro->exige_documento ? ' obrigatórios' : '' }}</div>
                                        @include('evidencias._lista', [
                                            'evidencias' => $registro->evidencias,
                                            'contextoRevisao' => true,
                                            'folha' => $folha,
                                            'item' => $item,
                                        ])
                                    </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-12"><span class="text-muted">Nenhum item registrado.</span></div>
                        @endforelse
                    </div>
                </div>

                @if ($conferencia?->apontamento)
                    <div class="alert alert-{{ $corConferencia }} mt-3 mb-0" role="status">
                        <strong>Apontamento da Central:</strong> {{ $conferencia->apontamento }}
                        <div class="small mt-1">{{ $conferencia->conferidoPor?->name }} · {{ $conferencia->conferido_em->format('d/m/Y H:i') }}</div>
                    </div>
                @endif

                @if ($folha->estaFinalizada())
                    <form method="POST" action="{{ route('painel-frequencias.servidores.conferir', [$folha, $item]) }}" class="border rounded p-3 mt-3">
                        @csrf
                        <fieldset>
                            <legend class="h6 fw-semibold">Resultado da conferência</legend>
                            @if ($errosConferencia->any())
                                <div class="alert alert-danger py-2" role="alert"><ul class="mb-0">@foreach ($errosConferencia->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
                            @endif
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4">
                                    <label for="status-conferencia-{{ $item->id }}" class="form-label">Resultado <span class="text-danger">*</span></label>
                                    <select id="status-conferencia-{{ $item->id }}" name="status" class="form-select @if($errosConferencia->has('status')) is-invalid @endif" required @if($errosConferencia->has('status')) aria-invalid="true" @endif>
                                        <option value="">Selecione...</option>
                                        @foreach ($statusConferencia as $status)
                                            <option value="{{ $status->value }}" @selected(($formComErro ? old('status') : $conferencia?->status?->value) === $status->value)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                    @if ($errosConferencia->has('status'))<div class="invalid-feedback">{{ $errosConferencia->first('status') }}</div>@endif
                                </div>
                                <div class="col-lg-6">
                                    <label for="apontamento-{{ $item->id }}" class="form-label">Apontamento</label>
                                    <textarea id="apontamento-{{ $item->id }}" name="apontamento" class="form-control @if($errosConferencia->has('apontamento')) is-invalid @endif" rows="2" minlength="10" maxlength="2000" aria-describedby="ajuda-apontamento-{{ $item->id }}" @if($errosConferencia->has('apontamento')) aria-invalid="true" @endif>{{ $formComErro ? old('apontamento') : $conferencia?->apontamento }}</textarea>
                                    @if ($errosConferencia->has('apontamento'))<div class="invalid-feedback">{{ $errosConferencia->first('apontamento') }}</div>@endif
                                    <div id="ajuda-apontamento-{{ $item->id }}" class="form-text">Obrigatório quando houver divergência.</div>
                                </div>
                                <div class="col-lg-2 d-grid"><button class="btn btn-primary" type="submit">Salvar análise</button></div>
                            </div>
                        </fieldset>
                    </form>
                @endif
            </div>
        </article>
    @endforeach
</div>

@if ($folha->estaFinalizada())
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body p-4">
            <h2 class="h6 fw-bold">Decisão da Central</h2>
            <p class="text-muted">Aprovação e devolução possuem bloqueios objetivos baseados na análise individual acima.</p>
            <div class="row g-4">
                <div class="col-lg-4">
                    @can('aprovar', $folha)
                        <form method="POST" action="{{ route('painel-frequencias.aprovar', $folha) }}" onsubmit="return confirm('Aprovar esta frequência mensal?');">
                            @csrf
                            <button class="btn btn-success w-100" type="submit" @disabled($resumoConferencia['conferidos'] !== $resumoConferencia['total'] || $resumoConferencia['total'] === 0)><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Aprovar frequência</button>
                        </form>
                        @if ($resumoConferencia['conferidos'] !== $resumoConferencia['total'])<div class="form-text">Confira todas as linhas sem divergência para habilitar.</div>@endif
                    @endcan
                </div>
                <div class="col-lg-8">
                    @can('devolver', $folha)
                        <form method="POST" action="{{ route('painel-frequencias.devolver', $folha) }}">
                            @csrf
                            <label for="motivo_devolucao" class="form-label fw-semibold">Orientação geral para correção</label>
                            <textarea id="motivo_devolucao" name="motivo_devolucao" rows="3" minlength="10" maxlength="2000" class="form-control" required>{{ old('motivo_devolucao') }}</textarea>
                            <button class="btn btn-outline-danger mt-2" type="submit" @disabled($resumoConferencia['divergentes'] === 0)><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Devolver ao setor</button>
                            @if ($resumoConferencia['divergentes'] === 0)<div class="form-text">Registre ao menos uma divergência para habilitar.</div>@endif
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@elseif ($folha->conferidoPor)
    <div class="alert alert-light border mt-4 mb-0">Conferida por <strong>{{ $folha->conferidoPor->name }}</strong> em {{ $folha->conferida_em?->format('d/m/Y H:i') }}.</div>
@endif
@endsection
