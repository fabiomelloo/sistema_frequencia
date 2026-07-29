@extends('layouts.app')

@section('title', 'Cobertura da Competência')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('admin.competencias.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar às competências</a>
        <h1 class="h4 fw-bold mt-2 mb-1">Cobertura da competência</h1>
        <p class="text-muted mb-0">{{ $competencia->descricao }}</p>
    </div>
    <span class="badge text-bg-{{ $competencia->status->cor() }} fs-6">{{ $competencia->status->label() }}</span>
</div>

@if (session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Setores obrigatórios</small><div class="h4 mb-0">{{ $resumo['setores_esperados'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Aprovadas</small><div class="h4 text-success mb-0">{{ $resumo['aprovadas'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Pendências</small><div class="h4 text-{{ $resumo['pendencias'] > 0 ? 'danger' : 'success' }} mb-0">{{ $resumo['pendencias'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Fechamento</small><div class="fw-bold text-{{ $resumo['pronta_para_fechar'] ? 'success' : 'warning' }} mt-1"><i class="bi bi-{{ $resumo['pronta_para_fechar'] ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $resumo['pronta_para_fechar'] ? 'Liberado' : 'Bloqueado' }}</div></div></div></div>
</div>

@if (! $resumo['pronta_para_fechar'])
    <div class="alert alert-warning" role="status">
        <strong>A competência ainda não pode ser fechada.</strong>
        Resolva as frequências não iniciadas, em preenchimento, devolvidas ou aguardando conferência.
    </div>
@elseif ($competencia->estaAberta())
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-3" role="status">
        <div><strong>Cobertura concluída.</strong> Todas as frequências obrigatórias estão aprovadas.</div>
        <form method="POST" action="{{ route('admin.competencias.fechar', $competencia) }}" onsubmit="return confirm('Fechar esta competência? O preenchimento será bloqueado.');">
            @csrf
            <button class="btn btn-success"><i class="bi bi-lock me-1"></i>Fechar competência</button>
        </form>
    </div>
@endif

<div class="d-flex flex-column gap-3">
    @forelse ($cobertura as $item)
        <article class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <h2 class="h6 fw-bold mb-1">{{ $item['setor']->nome }}</h2>
                        <div class="small text-muted">{{ $item['servidores_esperados'] }} servidor(es) elegível(is) no período</div>
                    </div>
                    <div class="col-7 col-lg-3">
                        <span class="badge text-bg-{{ $item['cor'] }}"><i class="bi bi-{{ $item['bloqueia_fechamento'] ? 'exclamation-circle' : 'check-circle' }} me-1"></i>{{ $item['label'] }}</span>
                    </div>
                    <div class="col-5 col-lg-2 small text-muted">
                        @if ($item['folha'])
                            {{ $item['servidores_na_folha'] }} registros na folha
                            @if ($item['divergencia_populacao'])
                                <div class="text-danger fw-semibold mt-1">
                                    {{ count($item['servidores_faltantes']) }} faltante(s),
                                    {{ count($item['servidores_excedentes']) }} excedente(s)
                                </div>
                            @endif
                        @else
                            Folha ainda não aberta
                        @endif
                    </div>
                    <div class="col-lg-2 d-flex justify-content-lg-end">
                        @if ($item['folha'])
                            <a href="{{ route('painel-frequencias.show', $item['folha']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye me-1"></i>Ver frequência</a>
                        @else
                            <span class="small text-muted">Aguardando o setor</span>
                        @endif
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Nenhum setor possui servidor elegível nesta competência.</div></div>
    @endforelse
</div>
@endsection
