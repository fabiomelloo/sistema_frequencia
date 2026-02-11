@extends('layouts.app')

@section('title', 'Meus Lançamentos — Sistema de Frequência')
@section('description', 'Lista de lançamentos setoriais')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Meus Lançamentos</h4>
        <p class="text-muted mb-0 small">Gerencie e aprove os lançamentos da sua equipe.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('lancamentos.lixeira') }}" class="btn btn-outline-secondary">
            <i class="bi bi-trash me-1"></i> Lixeira
        </a>
        <a href="{{ route('lancamentos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Novo Lançamento
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Filtros --}}
<div class="card filter-card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('lancamentos.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold text-secondary" style="font-size:0.8rem">Competência</label>
                <select name="competencia" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($competencias as $comp)
                        <option value="{{ $comp }}" @selected(($filtros['competencia'] ?? '') == $comp)>
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $comp)->format('M/Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold text-secondary" style="font-size:0.8rem">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos (exceto exp.)</option>
                    <option value="PENDENTE" @selected(($filtros['status'] ?? '') == 'PENDENTE')>Pendente</option>
                    <option value="CONFERIDO_SETORIAL" @selected(($filtros['status'] ?? '') == 'CONFERIDO_SETORIAL')>Conf. Setorial</option>
                    <option value="CONFERIDO" @selected(($filtros['status'] ?? '') == 'CONFERIDO')>Conf. Central</option>
                    <option value="REJEITADO" @selected(($filtros['status'] ?? '') == 'REJEITADO')>Rejeitado</option>
                    <option value="EXPORTADO" @selected(($filtros['status'] ?? '') == 'EXPORTADO')>Exportado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary" style="font-size:0.8rem">Servidor</label>
                <select name="servidor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($servidores as $s)
                        <option value="{{ $s->id }}" @selected(($filtros['servidor_id'] ?? '') == $s->id)>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary" style="font-size:0.8rem">Evento</label>
                <select name="evento_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($eventos as $e)
                        <option value="{{ $e->id }}" @selected(($filtros['evento_id'] ?? '') == $e->id)>{{ $e->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('lancamentos.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<form action="{{ route('lancamentos.aprovar-setorial-lote') }}" method="POST" id="formLote">
    @csrf
    
    {{-- Ações em Lote --}}
    <div class="card border-0 shadow-sm mb-3" id="bulkActions" style="display: none;">
        <div class="card-body py-2 d-flex align-items-center bg-light rounded">
            <i class="bi bi-check-all fs-4 me-2 text-primary"></i>
            <span class="fw-bold me-3"><span id="selectedCount">0</span> itens selecionados</span>
            <button type="submit" class="btn btn-success btn-sm text-white" onclick="return confirm('Confirmar aprovação dos itens selecionados?')">
                <i class="bi bi-check-lg me-1"></i> Aprovar Selecionados
            </button>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 40px;" class="ps-3">
                            <input type="checkbox" class="form-check-input" id="checkAll">
                        </th>
                        <th>Servidor</th>
                        <th>Evento / Detalhes</th>
                        <th>Referência</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lancamentos as $lancamento)
                        <tr>
                            <td class="ps-3">
                                @if($lancamento->status->value === 'PENDENTE')
                                    <input type="checkbox" class="form-check-input item-check" name="ids[]" value="{{ $lancamento->id }}">
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold">{{ $lancamento->servidor->nome }}</div>
                                <div class="text-muted small">{{ $lancamento->servidor->matricula }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="badge bg-secondary me-2">{{ $lancamento->evento->codigo_evento }}</span>
                                    <span>{{ $lancamento->evento->descricao }}</span>
                                </div>
                                <div class="small text-muted">
                                    @if($lancamento->dias_trabalhados) {{ $lancamento->dias_trabalhados }} dias @endif
                                    @if($lancamento->valor) R$ {{ number_format($lancamento->valor, 2, ',', '.') }} @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $lancamento->competencia)->format('M/Y') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background-color: {{ $lancamento->status->cor() }}">
                                    {{ $lancamento->status->label() }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    @if ($lancamento->status->value === 'PENDENTE')
                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                onclick="aprovarUm({{ $lancamento->id }})" 
                                                title="Aprovar">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    @endif

                                    <a href="{{ route('lancamentos.edit', $lancamento) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deletarUm({{ $lancamento->id }})"
                                            title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-1 opacity-25 mb-3"></i>
                                <div class="h5">Nenhum lançamento encontrado</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($lancamentos->hasPages())
            <div class="card-footer bg-white pt-3 border-top-0">
                {{ $lancamentos->links() }}
            </div>
        @endif
    </div>
</form>

{{-- Form Delete Hidden --}}
<form id="deleteForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

{{-- Form Aprovar Um Hidden --}}
<form id="approveForm" action="" method="POST" style="display: none;">
    @csrf
</form>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAll = document.getElementById('checkAll');
        const itemChecks = document.querySelectorAll('.item-check');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        function updateBulkActions() {
            const count = document.querySelectorAll('.item-check:checked').length;
            selectedCount.textContent = count;
            bulkActions.style.display = count > 0 ? 'block' : 'none';
        }

        checkAll.addEventListener('change', function() {
            itemChecks.forEach(check => check.checked = this.checked);
            updateBulkActions();
        });

        itemChecks.forEach(check => {
            check.addEventListener('change', updateBulkActions);
        });
    });

    function deletarUm(id) {
        if(confirm('Tem certeza que deseja excluir este lançamento?')) {
            const form = document.getElementById('deleteForm');
            form.action = `/lancamentos/${id}`;
            form.submit();
        }
    }

    function aprovarUm(id) {
        if(confirm('Aprovar este lançamento?')) {
            const form = document.getElementById('approveForm');
            form.action = `/lancamentos/${id}/aprovar-setorial`;
            form.submit();
        }
    }
</script>
@endsection
