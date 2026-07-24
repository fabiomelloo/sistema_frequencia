@extends('layouts.app')

@section('title', 'Editar Item Mensal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('admin.eventos.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar ao catálogo</a>
        <h1 class="h4 fw-bold mt-2 mb-1">Editar item mensal</h1>
        <p class="text-muted mb-0">{{ $evento->codigo_evento }} · {{ $evento->descricao }}</p>
    </div>
    <span class="badge text-bg-{{ $evento->regra_validada ? 'success' : 'warning' }} fs-6">
        <i class="bi bi-{{ $evento->regra_validada ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $evento->regra_validada ? 'Regra validada' : 'Revisão pendente' }}
    </span>
</div>

@if ($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise os campos indicados.</strong></div>@endif

@include('admin.eventos._form', ['evento' => $evento])
@endsection
