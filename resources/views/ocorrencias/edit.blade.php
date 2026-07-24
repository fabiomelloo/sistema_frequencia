@extends('layouts.app')

@section('title', 'Editar Ocorrência de Frequência')

@section('content')
<div class="mb-4">
    <h1 class="h4 fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Ocorrência</h1>
    <p class="text-muted mb-0">A competência precisa permanecer aberta para alteração.</p>
</div>

<form method="POST" action="{{ route('ocorrencias.update', $ocorrencia) }}">
    @csrf
    @method('PUT')
    @include('ocorrencias._form', ['textoBotao' => 'Salvar alterações'])
</form>
@endsection
