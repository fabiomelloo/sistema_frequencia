@extends('layouts.app')

@section('title', 'Detalhes da Ocorrência')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('ocorrencias.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Voltar às ocorrências</a>
        <h1 class="h4 fw-bold mt-2 mb-1">{{ $ocorrencia->tipo->label() }}</h1>
        <p class="text-muted mb-0">{{ $ocorrencia->servidor->nome }} — {{ $ocorrencia->competencia->descricao }}</p>
    </div>
    @can('update', $ocorrencia)
        <a href="{{ route('ocorrencias.edit', $ocorrencia) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    @endcan
</div>

@if (session('success'))
    <div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
@endif

<div class="alert alert-info d-flex gap-2 align-items-start" role="note">
    <i class="bi bi-info-circle mt-1"></i>
    <div><strong>Registro informativo.</strong> Esta ocorrência não criou nem alterou lançamentos financeiros.</div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <dl class="row mb-0 g-3">
            <dt class="col-sm-3 text-secondary">Servidor</dt><dd class="col-sm-9">{{ $ocorrencia->servidor->nome }} ({{ $ocorrencia->servidor->matricula }})</dd>
            <dt class="col-sm-3 text-secondary">Setor</dt><dd class="col-sm-9">{{ $ocorrencia->setor->nome }}</dd>
            <dt class="col-sm-3 text-secondary">Competência</dt><dd class="col-sm-9">{{ $ocorrencia->competencia->descricao }}</dd>
            <dt class="col-sm-3 text-secondary">Período contínuo</dt>
            <dd class="col-sm-9">{{ $ocorrencia->data_inicio ? $ocorrencia->data_inicio->format('d/m/Y').' a '.$ocorrencia->data_fim->format('d/m/Y') : 'Não informado' }}</dd>
            <dt class="col-sm-3 text-secondary">Dias específicos</dt>
            <dd class="col-sm-9">{{ $ocorrencia->dias->isEmpty() ? 'Não informados' : $ocorrencia->dias->pluck('data')->map->format('d/m/Y')->join(', ') }}</dd>
            <dt class="col-sm-3 text-secondary">Justificação</dt>
            <dd class="col-sm-9">{{ is_null($ocorrencia->justificada) ? 'Não informada' : ($ocorrencia->justificada ? 'Justificada' : 'Não justificada') }}</dd>
            <dt class="col-sm-3 text-secondary">Comprovação</dt><dd class="col-sm-9">{{ $ocorrencia->possui_comprovacao ? 'Informada' : 'Não informada' }}</dd>
            <dt class="col-sm-3 text-secondary">Referência</dt><dd class="col-sm-9">{{ $ocorrencia->referencia_documento ?: 'Não informada' }}</dd>
            <dt class="col-sm-3 text-secondary">Observação original</dt><dd class="col-sm-9 text-break">{{ $ocorrencia->observacao_original ?: 'Não informada' }}</dd>
            <dt class="col-sm-3 text-secondary">Registrado por</dt><dd class="col-sm-9">{{ $ocorrencia->criadoPor->name }} em {{ $ocorrencia->created_at->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
</div>

<section class="card shadow-sm border-0 mb-4" aria-labelledby="titulo-evidencias">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <h2 id="titulo-evidencias" class="h6 fw-bold mb-1">Documentos comprobatórios</h2>
                <p class="small text-muted mb-0">Arquivos vinculados diretamente a esta ocorrência e disponíveis somente para usuários autorizados.</p>
            </div>
            @if ($ocorrencia->possui_comprovacao && $ocorrencia->evidencias->isEmpty())
                <span class="badge text-bg-warning align-self-start">Documento pendente</span>
            @endif
        </div>

        @include('evidencias._lista', ['evidencias' => $ocorrencia->evidencias, 'podeRemover' => true])

        @can('update', $ocorrencia)
            @include('evidencias._upload', [
                'actionEvidencia' => route('ocorrencias.evidencias.store', $ocorrencia),
                'bagEvidencia' => 'evidencia_ocorrencias_frequencia_'.$ocorrencia->id,
                'idEvidencia' => 'ocorrencia-'.$ocorrencia->id,
            ])
        @endcan
    </div>
</section>

@can('delete', $ocorrencia)
    <div class="d-flex justify-content-end">
        <form method="POST" action="{{ route('ocorrencias.destroy', $ocorrencia) }}" onsubmit="return confirm('Remover esta ocorrência? O registro ficará preservado na auditoria.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Remover ocorrência</button>
        </form>
    </div>
@endcan
@endsection
