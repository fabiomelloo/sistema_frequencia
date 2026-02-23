@extends('layouts.app')

@section('title', 'Gerenciar Competências')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="text-primary fw-bold">Gerenciar Competências</h2>
        <p class="text-muted">Controle de abertura e fechamento de períodos de lançamento.</p>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#abrirCompetenciaModal">
            <i class="bi bi-plus-circle me-1"></i> Abrir Nova Competência
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
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Competência</th>
                        <th>Status</th>
                        <th>Dias Úteis</th>
                        <th>Data Limite</th>
                        <th>Aberto Por</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($competencias as $competencia)
                        <tr>
                            <td class="ps-4 fw-bold">{{ $competencia->descricao }}</td>
                            <td>
                                @if($competencia->status === \App\Enums\CompetenciaStatus::ABERTA)
                                    <span class="badge bg-success">ABERTA</span>
                                @else
                                    <span class="badge bg-secondary">FECHADA</span>
                                @endif
                            </td>
                            <td>{{ \App\Models\Competencia::obterDiasUteis($competencia->referencia) }} dias</td>
                            <td>{{ $competencia->data_limite->format('d/m/Y') }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white me-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                        {{ substr($competencia->criadoPor->name, 0, 1) }}
                                    </div>
                                    <small>{{ $competencia->criadoPor->name }}</small>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                @if($competencia->status === \App\Enums\CompetenciaStatus::ABERTA)
                                    <form action="{{ route('admin.competencias.fechar', $competencia->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja fechar esta competência? Novos lançamentos serão bloqueados.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-lock me-1"></i> Fechar
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.competencias.reabrir', $competencia->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja reabrir esta competência?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-unlock me-1"></i> Reabrir
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x display-4 d-block mb-3"></i>
                                Nenhuma competência registrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($competencias->hasPages())
        <div class="card-footer bg-white border-top-0 pt-3">
            {{ $competencias->links() }}
        </div>
    @endif
</div>

<!-- Modal Abrir Competência -->
<div class="modal fade" id="abrirCompetenciaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Abrir Nova Competência</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.competencias.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="competencia" class="form-label">Mês/Ano (MM/AAAA)</label>
                        <input type="month" class="form-control" id="referencia" name="referencia" required 
                               value="{{ now()->addMonth()->format('Y-m') }}">
                        <div class="form-text">Selecione o mês e ano da competência.</div>
                    </div>
                    <div class="mb-3">
                        <label for="data_limite" class="form-label">Data Limite para Lançamentos</label>
                        <input type="date" class="form-control" id="data_limite" name="data_limite" required
                               value="{{ now()->addMonth()->endOfMonth()->format('Y-m-d') }}">
                        <div class="form-text">Data limite para os setores enviarem os lançamentos.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Abrir Competência
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
