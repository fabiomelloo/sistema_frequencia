@extends('layouts.app')

@section('title', 'Minhas Delegações')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="text-primary fw-bold">Delegação de Acesso</h2>
        <p class="text-muted">Permita que outros usuários acessem seu setor temporariamente.</p>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaDelegacaoModal">
            <i class="bi bi-person-plus me-1"></i> Nova Delegação
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">Delegações Ativas e Histórico</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Usuário Delegado</th>
                        <th>Setor</th>
                        <th>Início</th>
                        <th>Fim</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($delegacoes as $delegacao)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-secondary text-white me-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        {{ substr($delegacao->delegado->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $delegacao->delegado->name }}</div>
                                        <small class="text-muted">{{ $delegacao->delegado->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $delegacao->setor->nome }}</td>
                            <td>{{ $delegacao->data_inicio->format('d/m/Y') }}</td>
                            <td>{{ $delegacao->data_fim->format('d/m/Y') }}</td>
                            <td>
                                @if($delegacao->ativa)
                                    <span class="badge bg-success">ATIVA</span>
                                @else
                                    <span class="badge bg-secondary">INATIVA</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if($delegacao->ativa)
                                    <form action="{{ route('lancamentos.delegacoes.revogar', $delegacao->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja revogar esta delegação?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i> Revogar
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-4 d-block mb-3 opacity-25"></i>
                                Nenhuma delegação registrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nova Delegação -->
<div class="modal fade" id="novaDelegacaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nova Delegação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('lancamentos.delegacoes.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="delegado_id" class="form-label">Usuário para Delegar</label>
                        <select class="form-select" id="delegado_id" name="delegado_id" required>
                            <option value="">Selecione um usuário...</option>
                            @foreach($usuariosDisponiveis as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">O usuário terá acesso completo aos lançamentos do seu setor.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" required value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" required value="{{ now()->addDays(7)->format('Y-m-d') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Criar Delegação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
