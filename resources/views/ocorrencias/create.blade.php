@extends('layouts.app')

@section('title', 'Nova Ocorrência de Frequência')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar2-plus me-2"></i>Nova Ocorrência</h1>
        <p class="text-muted mb-0">Registre o fato observado sem criar efeito financeiro automático.</p>
    </div>
</div>

<form method="POST" action="{{ route('ocorrencias.store') }}">
    @csrf
    @include('ocorrencias._form', ['textoBotao' => 'Registrar ocorrência'])
</form>
@endsection
