@extends('layouts.app')

@section('title', 'Detalhes do Lançamento — Sistema de Frequência')
@section('description', 'Visualização completa do lançamento setorial com histórico de conferência e validação.')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Detalhes do Lançamento</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('lancamentos.index') }}" class="btn btn-secondary">Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        Informações do Lançamento
                        @if ($lancamento->isPendente())
                            <span class="badge bg-warning text-dark float-end">PENDENTE</span>
                        @elseif ($lancamento->isConferidoSetorial())
                            <span class="badge bg-info text-white float-end">CONFERIDO SETORIAL</span>
                        @elseif ($lancamento->isConferido())
                            <span class="badge bg-success float-end">CONFERIDO</span>
                        @elseif ($lancamento->isRejeitado())
                            <span class="badge bg-danger float-end">REJEITADO</span>
                        @elseif ($lancamento->isExportado())
                            <span class="badge bg-secondary float-end">EXPORTADO</span>
                        @elseif ($lancamento->isEstornado())
                            <span class="badge bg-dark text-white float-end">ESTORNADO</span>
                        @elseif ($lancamento->isEstornoSolicitado())
                            <span class="badge bg-warning text-dark float-end">ESTORNO SOLICITADO</span>
                        @elseif ($lancamento->isCancelado())
                            <span class="badge bg-danger float-end">CANCELADO</span>
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

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Competência:</strong><br>
                            {{ $lancamento->competencia }}
                        </div>
                    </div>

                    {{-- Campos de dias --}}
                    @if ($lancamento->dias_trabalhados)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Dias Trabalhados:</strong><br>
                                {{ $lancamento->dias_trabalhados }}
                            </div>
                            @if ($lancamento->dias_noturnos)
                                <div class="col-md-6">
                                    <strong>Dias Noturnos:</strong><br>
                                    {{ $lancamento->dias_noturnos }}
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Campos de valor --}}
                    @if ($lancamento->valor || $lancamento->valor_gratificacao)
                        <div class="row mb-3">
                            @if ($lancamento->valor)
                                <div class="col-md-6">
                                    <strong>Valor:</strong><br>
                                    R$ {{ number_format($lancamento->valor, 2, ',', '.') }}
                                </div>
                            @endif
                            @if ($lancamento->valor_gratificacao)
                                <div class="col-md-6">
                                    <strong>Gratificação:</strong><br>
                                    R$ {{ number_format($lancamento->valor_gratificacao, 2, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Percentuais --}}
                    @if ($lancamento->porcentagem_insalubridade || $lancamento->porcentagem_periculosidade)
                        <div class="row mb-3">
                            @if ($lancamento->porcentagem_insalubridade)
                                <div class="col-md-6">
                                    <strong>Insalubridade:</strong><br>
                                    {{ $lancamento->porcentagem_insalubridade }}%
                                </div>
                            @endif
                            @if ($lancamento->porcentagem_periculosidade)
                                <div class="col-md-6">
                                    <strong>Periculosidade:</strong><br>
                                    {{ $lancamento->porcentagem_periculosidade }}%
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Adicionais --}}
                    @if ($lancamento->adicional_turno || $lancamento->adicional_noturno)
                        <div class="row mb-3">
                            @if ($lancamento->adicional_turno)
                                <div class="col-md-6">
                                    <strong>Adicional de Turno:</strong><br>
                                    R$ {{ number_format($lancamento->adicional_turno, 2, ',', '.') }}
                                </div>
                            @endif
                            @if ($lancamento->adicional_noturno)
                                <div class="col-md-6">
                                    <strong>Adicional Noturno:</strong><br>
                                    R$ {{ number_format($lancamento->adicional_noturno, 2, ',', '.') }}
                                </div>
                            @endif
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

                    {{-- Conferência Setorial --}}
                    @if ($lancamento->conferido_setorial_em)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Conferido pelo Setor em:</strong><br>
                                {{ $lancamento->conferido_setorial_em->format('d/m/Y H:i:s') }}
                            </div>
                            <div class="col-md-6">
                                <strong>Conferido por:</strong><br>
                                {{ $lancamento->conferidoSetorialPor?->name ?? 'N/A' }}
                            </div>
                        </div>
                    @endif

                    @if ($lancamento->validated_at)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Data de Validação (Central):</strong><br>
                                {{ $lancamento->validated_at->format('d/m/Y H:i:s') }}
                            </div>
                            <div class="col-md-6">
                                <strong>Validado por:</strong><br>
                                {{ $lancamento->validador?->name ?? 'N/A' }}
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

                    @if ($lancamento->motivo_estorno)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Motivo do Estorno:</strong><br>
                                <div class="alert alert-secondary mb-0">
                                    {{ $lancamento->motivo_estorno }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
