@extends('layouts.app')

@section('title', 'Catálogo de Itens Mensais')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-ui-checks-grid me-2"></i>Catálogo de itens mensais</h1>
        <p class="text-muted mb-0">Regras que determinam como cada informação será apresentada e validada na frequência.</p>
    </div>
    @can('create', \App\Models\EventoFolha::class)
        <a href="{{ route('admin.eventos.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Novo item</a>
    @endcan
</div>

@if (session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}</div>@endif

<div class="alert alert-info" role="note">
    Eventos migrados permanecem ativos, mas começam com <strong>revisão pendente</strong>. A classificação inferida preserva as exigências anteriores e só se torna regra oficial após conferência administrativa.
</div>

<div class="d-flex flex-column gap-3">
    @forelse ($eventos as $evento)
        <article class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4">
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge text-bg-light border text-dark">{{ $evento->sigla ?: $evento->codigo_evento }}</span>
                            <div>
                                <h2 class="h6 fw-bold mb-1">{{ $evento->descricao }}</h2>
                                <div class="small text-muted">Código {{ $evento->codigo_evento }} · {{ $evento->tipo_evento?->label() ?? 'Sem grupo' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <div class="small text-muted">Forma</div>
                        <div class="small fw-semibold">{{ $evento->unidade_lancamento?->label() ?? 'Não definida' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <div class="small text-muted">Origem</div>
                        <div class="small fw-semibold">{{ $evento->origem_informacao?->label() ?? 'Não definida' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <span class="badge text-bg-{{ $evento->regra_validada ? 'success' : 'warning' }}">
                            <i class="bi bi-{{ $evento->regra_validada ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $evento->regra_validada ? 'Regra validada' : 'Revisão pendente' }}
                        </span>
                        <div class="small text-muted mt-1">{{ $evento->ativo ? 'Disponível' : 'Inativo' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-2 d-flex flex-wrap justify-content-lg-end gap-2">
                        <a href="{{ route('admin.eventos.show', $evento) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye me-1"></i>Ver</a>
                        @can('update', $evento)<a href="{{ route('admin.eventos.edit', $evento) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>@endcan
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Nenhum item mensal configurado.</div></div>
    @endforelse
</div>

@if ($eventos->hasPages())<div class="mt-4">{{ $eventos->links() }}</div>@endif
@endsection
