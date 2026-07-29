@extends('layouts.app')

@section('title', 'Novo Item Mensal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('admin.eventos.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar ao catálogo</a>
        <h1 class="h4 fw-bold mt-2 mb-1">Novo item mensal</h1>
        <p class="text-muted mb-0">Configure a regra antes de disponibilizar o item para os setores.</p>
    </div>
</div>

@if ($errors->any())<div class="alert alert-danger" role="alert"><strong>Revise os campos indicados.</strong></div>@endif

@include('admin.eventos._form', ['evento' => new \App\Models\EventoFolha])
@endsection
