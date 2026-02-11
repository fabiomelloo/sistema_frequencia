@extends('layouts.app')

@section('title', 'Painel de Conferência — Sistema de Frequência')
@section('description', 'Conferência e aprovação de lançamentos setoriais')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-check me-2"></i>Painel de Conferência</h4>
    <div class="d-flex gap-2">
        @if ($statusAtual === 'CONFERIDO')
            <form action="{{ route('painel.exportar') }}" method="POST" style="display: inline;">
                @csrf
                <input type="hidden" name="competencia" value="{{ $filtros['competencia'] ?? '' }}">
                <button type="submit" class="btn btn-success" onclick="return confirm('Exportar todos os lançamentos CONFERIDO?')">
                    <i class="bi bi-download me-1"></i> Exportar TXT
                </button>
            </form>
        @endif
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle me-1"></i> </strong>{{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Status tabs --}}
<div class="card filter-card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted fw-semibold me-2" style="font-size:0.85rem">Status:</span>
            
            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'CONFERIDO_SETORIAL'])) }}" class="btn btn-sm {{ $statusAtual === 'CONFERIDO_SETORIAL' ? 'btn-info text-white' : 'btn-outline-info' }}">
                <i class="bi bi-check2-circle me-1"></i>Conf. Setorial <span class="badge bg-white text-dark ms-1">{{ $contadores['CONFERIDO_SETORIAL'] ?? 0 }}</span>
            </a>

            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'PENDENTE'])) }}" class="btn btn-sm {{ $statusAtual === 'PENDENTE' ? 'btn-warning' : 'btn-outline-warning' }}">
                <i class="bi bi-hourglass-split me-1"></i>Pendentes <span class="badge bg-dark ms-1">{{ $contadores['PENDENTE'] ?? 0 }}</span>
            </a>
            
            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'CONFERIDO'])) }}" class="btn btn-sm {{ $statusAtual === 'CONFERIDO' ? 'btn-success' : 'btn-outline-success' }}">
                <i class="bi bi-check-circle me-1"></i>Conferidos <span class="badge bg-dark ms-1">{{ $contadores['CONFERIDO'] ?? 0 }}</span>
            </a>
            
            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'REJEITADO'])) }}" class="btn btn-sm {{ $statusAtual === 'REJEITADO' ? 'btn-danger' : 'btn-outline-danger' }}">
                <i class="bi bi-x-circle me-1"></i>Rejeitados <span class="badge bg-dark ms-1">{{ $contadores['REJEITADO'] ?? 0 }}</span>
            </a>
            
            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'EXPORTADO'])) }}" class="btn btn-sm {{ $statusAtual === 'EXPORTADO' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="bi bi-download me-1"></i>Exportados <span class="badge bg-dark ms-1">{{ $contadores['EXPORTADO'] ?? 0 }}</span>
            </a>

            <a href="{{ route('painel.index', array_merge($filtros, ['status' => 'ESTORNADO'])) }}" class="btn btn-sm {{ $statusAtual === 'ESTORNADO' ? 'btn-dark' : 'btn-outline-dark' }}">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Estornados <span class="badge bg-white text-dark ms-1">{{ $contadores['ESTORNADO'] ?? 0 }}</span>
            </a>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card filter-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('painel.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="{{ $statusAtual }}">
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Competência</label>
                <select name="competencia" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($competencias as $comp)
                        <option value="{{ $comp }}" @selected(($filtros['competencia'] ?? '') == $comp)>{{ $comp }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Setor</label>
                <select name="setor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($setores as $s)
                        <option value="{{ $s->id }}" @selected(($filtros['setor_id'] ?? '') == $s->id)>{{ $s->sigla ?? $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Evento</label>
                <select name="evento_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($eventos as $e)
                        <option value="{{ $e->id }}" @selected(($filtros['evento_id'] ?? '') == $e->id)>{{ $e->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="font-size:0.8rem">Busca</label>
                <input type="text" name="busca" class="form-control form-control-sm" placeholder="Matrícula / nome" value="{{ $filtros['busca'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('painel.index', ['status' => $statusAtual]) }}" class="btn btn-outline-secondary btn-sm" title="Limpar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela com seleção para aprovação em lote --}}
<form action="{{ route('painel.aprovar-lote') }}" method="POST" id="formLote">
    @csrf
    <div class="card">
        @if (in_array($statusAtual, ['PENDENTE', 'CONFERIDO_SETORIAL']) && $lancamentos->count() > 0)
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label fw-semibold" for="selectAll" style="font-size:0.85rem">Selecionar todos</label>
                </div>
                <button type="submit" class="btn btn-success btn-sm" id="btnAprovarLote" style="display:none" onclick="return confirm('Aprovar os lançamentos selecionados?')">
                    <i class="bi bi-check-all me-1"></i> Aprovar Selecionados (<span id="countSelecionados">0</span>)
                </button>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        @if (in_array($statusAtual, ['PENDENTE', 'CONFERIDO_SETORIAL']))
                            <th style="width:40px"></th>
                        @endif
                        <th>Matrícula</th>
                        <th>Servidor</th>
                        <th>Evento / Detalhes</th>
                        <th>Setor</th>
                        <th>Referência</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lancamentos as $lancamento)
                        <tr @class([
                            'table-warning' => $lancamento->isPendente(),
                            'table-info' => $lancamento->isConferidoSetorial(),
                            'table-success' => $lancamento->isConferido(),
                            'table-danger' => $lancamento->isRejeitado(),
                            'table-dark text-white' => $lancamento->status->value === 'ESTORNADO',
                        ])>
                            @if (in_array($statusAtual, ['PENDENTE', 'CONFERIDO_SETORIAL']))
                                <td>
                                    <input class="form-check-input item-check" type="checkbox" name="lancamento_ids[]" value="{{ $lancamento->id }}">
                                </td>
                            @endif
                            <td><strong>{{ $lancamento->servidor->matricula }}</strong></td>
                            <td>{{ $lancamento->servidor->nome }}</td>
                            <td>
                                <div>{{ $lancamento->evento->descricao }}</div>
                                <div class="small opacity-75">
                                    @if($lancamento->valor) R$ {{ number_format($lancamento->valor, 2, ',', '.') }} @endif
                                    @if($lancamento->dias_trabalhados) {{ $lancamento->dias_trabalhados }} dias @endif
                                </div>
                            </td>
                            <td>{{ $lancamento->setorOrigem->sigla ?? $lancamento->setorOrigem->nome }}</td>
                            <td>{{ $lancamento->competencia }}</td>
                            <td>
                                <span class="badge" style="background-color: {{ $lancamento->status->cor() }}">
                                    {{ $lancamento->status->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{ route('painel.show', $lancamento) }}" class="btn btn-sm btn-outline-light text-dark border-secondary" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    @if ($lancamento->isConferidoSetorial() || $lancamento->isPendente())
                                        <form action="{{ route('painel.aprovar', $lancamento) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejeicaoModal{{ $lancamento->id }}" title="Rejeitar">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @endif

                                    @if ($lancamento->isExportado())
                                        <form action="{{ route('painel.estornar', $lancamento) }}" method="POST" onsubmit="return confirm('ATENÇÃO: Deseja realmente estornar este lançamento exportado?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-dark" title="Estornar Exportação">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                {{-- Modal de Rejeição --}}
                                @if ($lancamento->isConferidoSetorial() || $lancamento->isPendente())
                                    <div class="modal fade text-dark" id="rejeicaoModal{{ $lancamento->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="bi bi-x-circle me-1"></i>Rejeitar Lançamento</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="{{ route('painel.rejeitar', $lancamento) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <p class="text-muted mb-2">
                                                            <strong>{{ $lancamento->servidor->nome }}</strong> — {{ $lancamento->evento->descricao }}
                                                        </p>
                                                        <div class="mb-3">
                                                            <label for="motivo{{ $lancamento->id }}" class="form-label fw-semibold">Motivo da Rejeição <span class="text-danger">*</span></label>
                                                            <textarea name="motivo_rejeicao" id="motivo{{ $lancamento->id }}" class="form-control" rows="4" required placeholder="Descreva o motivo da rejeição..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Rejeitar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-1 opacity-25 mb-3"></i>
                                <div class="h5">Nenhum lançamento encontrado em "{{ \App\Enums\LancamentoStatus::tryFrom($statusAtual)?->label() ?? $statusAtual }}"</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@if ($lancamentos->hasPages())
    <nav class="mt-3">{{ $lancamentos->links() }}</nav>
@endif
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const items = document.querySelectorAll('.item-check');
    const btnLote = document.getElementById('btnAprovarLote');
    const countEl = document.getElementById('countSelecionados');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            items.forEach(i => i.checked = this.checked);
            updateCount();
        });
        items.forEach(i => i.addEventListener('change', updateCount));
    }

    function updateCount() {
        const checked = document.querySelectorAll('.item-check:checked').length;
        if (countEl) countEl.textContent = checked;
        if (btnLote) btnLote.style.display = checked > 0 ? '' : 'none';
    }
});
</script>
@endsection
