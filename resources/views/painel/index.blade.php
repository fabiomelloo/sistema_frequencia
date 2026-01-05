@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Painel de Conferência de Lançamentos</h2>
        </div>
        <div class="col-md-4 text-end">
            @if ($statusAtual === 'CONFERIDO')
                <form action="{{ route('painel.exportar') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success" onclick="return confirm('Exportar todos os lançamentos CONFERIDO?')">
                        Exportar TXT
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Erro:</strong>
            {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Filtrar por Status</h5>
            <div class="btn-group" role="group">
                <a href="{{ route('painel.index', ['status' => 'PENDENTE']) }}" class="btn {{ $statusAtual === 'PENDENTE' ? 'btn-danger active' : 'btn-outline-danger' }}">
                    PENDENTE <span class="badge bg-danger ms-2">{{ $contadores['PENDENTE'] }}</span>
                </a>
                <a href="{{ route('painel.index', ['status' => 'CONFERIDO']) }}" class="btn {{ $statusAtual === 'CONFERIDO' ? 'btn-success active' : 'btn-outline-success' }}">
                    CONFERIDO <span class="badge bg-success ms-2">{{ $contadores['CONFERIDO'] }}</span>
                </a>
                <a href="{{ route('painel.index', ['status' => 'REJEITADO']) }}" class="btn {{ $statusAtual === 'REJEITADO' ? 'btn-warning active' : 'btn-outline-warning' }}">
                    REJEITADO <span class="badge bg-warning ms-2">{{ $contadores['REJEITADO'] }}</span>
                </a>
                <a href="{{ route('painel.index', ['status' => 'EXPORTADO']) }}" class="btn {{ $statusAtual === 'EXPORTADO' ? 'btn-secondary active' : 'btn-outline-secondary' }}">
                    EXPORTADO <span class="badge bg-secondary ms-2">{{ $contadores['EXPORTADO'] }}</span>
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matrícula</th>
                        <th>Servidor</th>
                        <th>Evento</th>
                        <th>Setor</th>
                        <th>Valor</th>
                        <th>Data Lançamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lancamentos as $lancamento)
                        <tr @if ($lancamento->isPendente()) class="table-danger" @elseif ($lancamento->isConferido()) class="table-success" @elseif ($lancamento->isExportado()) class="table-secondary" @endif>
                            <td>
                                <strong>{{ $lancamento->servidor->matricula }}</strong>
                            </td>
                            <td>{{ $lancamento->servidor->nome }}</td>
                            <td>{{ $lancamento->evento->descricao }}</td>
                            <td>{{ $lancamento->setorOrigem->sigla ?? $lancamento->setorOrigem->nome }}</td>
                            <td>
                                @if ($lancamento->valor)
                                    R$ {{ number_format($lancamento->valor, 2, ',', '.') }}
                                @else
                                    ---
                                @endif
                            </td>
                            <td>{{ $lancamento->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('painel.show', $lancamento) }}" class="btn btn-sm btn-info" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if ($lancamento->isPendente())
                                    <form action="{{ route('painel.aprovar', $lancamento) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejeicaoModal{{ $lancamento->id }}" title="Rejeitar">
                                        <i class="bi bi-x-circle"></i>
                                    </button>

                                    <div class="modal fade" id="rejeicaoModal{{ $lancamento->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Rejeitar Lançamento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="{{ route('painel.rejeitar', $lancamento) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="motivo" class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                                                            <textarea name="motivo_rejeicao" id="motivo" class="form-control" rows="4" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-danger">Rejeitar</button>
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
                            <td colspan="7" class="text-center py-4">
                                Nenhum lançamento encontrado com este status.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($lancamentos->hasPages())
        <nav aria-label="Page navigation" class="mt-4">
            {{ $lancamentos->links() }}
        </nav>
    @endif
</div>
@endsection
