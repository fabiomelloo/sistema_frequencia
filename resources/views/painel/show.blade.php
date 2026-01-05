@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Detalhes do Lançamento</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('painel.index') }}" class="btn btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        Informações do Lançamento
                        @if ($lancamento->isPendente())
                            <span class="badge bg-danger float-end">PENDENTE</span>
                        @elseif ($lancamento->isConferido())
                            <span class="badge bg-success float-end">CONFERIDO</span>
                        @elseif ($lancamento->isRejeitado())
                            <span class="badge bg-warning float-end">REJEITADO</span>
                        @elseif ($lancamento->isExportado())
                            <span class="badge bg-secondary float-end">EXPORTADO</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Matrícula do Servidor:</strong><br>
                            {{ $lancamento->servidor->matricula }}
                        </div>
                        <div class="col-md-6">
                            <strong>Nome do Servidor:</strong><br>
                            {{ $lancamento->servidor->nome }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Setor de Origem:</strong><br>
                            {{ $lancamento->setorOrigem->nome }} ({{ $lancamento->setorOrigem->sigla }})
                        </div>
                        <div class="col-md-6">
                            <strong>Evento:</strong><br>
                            {{ $lancamento->evento->codigo_evento }} - {{ $lancamento->evento->descricao }}
                        </div>
                    </div>

                    @if ($lancamento->dias_lancados)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Dias Lançados:</strong><br>
                                {{ $lancamento->dias_lancados }}
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->valor)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Valor:</strong><br>
                                R$ {{ number_format($lancamento->valor, 2, ',', '.') }}
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->porcentagem_insalubridade)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Porcentagem de Insalubridade:</strong><br>
                                {{ $lancamento->porcentagem_insalubridade }}%
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->observacao)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Observação:</strong><br>
                                {{ $lancamento->observacao }}
                            </div>
                        </div>
                    @endif

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Data de Lançamento:</strong><br>
                            {{ $lancamento->created_at->format('d/m/Y H:i:s') }}
                        </div>
                    </div>

                    @if ($lancamento->validated_at)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Data de Validação:</strong><br>
                                {{ $lancamento->validated_at->format('d/m/Y H:i:s') }}
                            </div>
                            <div class="col-md-6">
                                <strong>Validado por:</strong><br>
                                {{ $lancamento->validador->name }}
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->isRejeitado() && $lancamento->motivo_rejeicao)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Motivo da Rejeição:</strong><br>
                                <div class="alert alert-warning mb-0">
                                    {{ $lancamento->motivo_rejeicao }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->exportado_em)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Exportado em:</strong><br>
                                {{ $lancamento->exportado_em->format('d/m/Y H:i:s') }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
