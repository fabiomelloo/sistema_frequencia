@extends('layouts.app')

@section('title', 'Notificações — Sistema de Frequência')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>Minhas Notificações</h4>
    @if ($notificacoes->where('lida_em', null)->count() > 0)
        <form action="{{ route('notificacoes.ler-todas') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-check-all me-1"></i> Marcar todas como lidas
            </button>
        </form>
    @endif
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($notificacoes as $notif)
            <div class="list-group-item {{ $notif->isLida() ? '' : 'list-group-item-light' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-start">
                        <div class="me-3 mt-1">
                            @if ($notif->tipo === 'APROVADO')
                                <i class="bi bi-check-circle-fill text-success" style="font-size:1.3rem"></i>
                            @elseif ($notif->tipo === 'REJEITADO')
                                <i class="bi bi-x-circle-fill text-danger" style="font-size:1.3rem"></i>
                            @elseif ($notif->tipo === 'EXPORTADO')
                                <i class="bi bi-download text-info" style="font-size:1.3rem"></i>
                            @else
                                <i class="bi bi-info-circle-fill text-primary" style="font-size:1.3rem"></i>
                            @endif
                        </div>
                        <div>
                            <h6 class="mb-1 {{ $notif->isLida() ? 'text-muted' : 'fw-bold' }}">{{ $notif->titulo }}</h6>
                            <p class="mb-1" style="font-size:0.9rem">{{ $notif->mensagem }}</p>
                            <small class="text-muted">{{ $notif->created_at->diffForHumans() }}</small>
                        </div>
                    </div>
                    @if (!$notif->isLida())
                        <form action="{{ route('notificacoes.ler', $notif) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                    @else
                        <span class="badge bg-light text-muted">Lida</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="list-group-item text-center py-5 text-muted">
                <i class="bi bi-bell-slash" style="font-size:2.5rem"></i>
                <div class="mt-2">Nenhuma notificação encontrada.</div>
            </div>
        @endforelse
    </div>
</div>

@if ($notificacoes->hasPages())
    <nav class="mt-3">{{ $notificacoes->links() }}</nav>
@endif
@endsection
