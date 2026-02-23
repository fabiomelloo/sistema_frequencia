@extends('layouts.app')

@section('title', 'Configurações do Sistema')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-gear me-2 text-primary"></i> Configurações do Sistema
        </h1>
        <p class="text-muted mb-0">Gerencie os parâmetros globais e regras de negócio do sistema.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">Parâmetros Atuais</h6>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('admin.configuracoes.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Parâmetro</th>
                                    <th style="width: 45%;">Descrição</th>
                                    <th style="width: 30%;">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($configuracoes as $config)
                                    <tr>
                                        <td>
                                            <code>{{ $config->chave }}</code>
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $config->descricao ?? 'Sem descrição' }}</span>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="{{ $config->chave }}" value="{{ $config->valor }}">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            Nenhuma configuração encontrada no banco de dados.
                                        </td>
                                    </tr>
                                @endforelse
                                <!-- Entradas de config extras que faltam no DB mas são esperadas no código -->
                                @if(!$configuracoes->contains('chave', 'meses_retroativos'))
                                    <tr>
                                        <td><code>meses_retroativos</code></td>
                                        <td><span class="text-muted small">Limite de meses para lançamentos retroativos (Padrão: 3)</span></td>
                                        <td><input type="number" class="form-control" name="meses_retroativos" value="3"></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'limite_orcamento_retroativo'))
                                    <tr>
                                        <td><code>limite_orcamento_retroativo</code></td>
                                        <td><span class="text-muted small">Limite orçamentário total para lançamentos retroativos no mês atual</span></td>
                                        <td><input type="number" step="0.01" class="form-control" name="limite_orcamento_retroativo" value=""></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'limite_delegacoes_setor'))
                                    <tr>
                                        <td><code>limite_delegacoes_setor</code></td>
                                        <td><span class="text-muted small">Máximo de delegações ativas por setor simultaneamente (Padrão: 3)</span></td>
                                        <td><input type="number" class="form-control" name="limite_delegacoes_setor" value="3"></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'duracao_maxima_delegacao_dias'))
                                    <tr>
                                        <td><code>duracao_maxima_delegacao_dias</code></td>
                                        <td><span class="text-muted small">Duração máxima de uma delegação ativa em dias (Padrão: 90)</span></td>
                                        <td><input type="number" class="form-control" name="duracao_maxima_delegacao_dias" value="90"></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'maximo_dias_noturnos'))
                                    <tr>
                                        <td><code>maximo_dias_noturnos</code></td>
                                        <td><span class="text-muted small">Máximo de dias de plantão noturno permitidos por mês (Padrão: 15)</span></td>
                                        <td><input type="number" class="form-control" name="maximo_dias_noturnos" value="15"></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'teto_adicional_noturno'))
                                    <tr>
                                        <td><code>teto_adicional_noturno</code></td>
                                        <td><span class="text-muted small">Valor máximo financeiro permitido para o adicional noturno (Padrão: 500.00)</span></td>
                                        <td><input type="number" step="0.01" class="form-control" name="teto_adicional_noturno" value="500.00"></td>
                                    </tr>
                                @endif
                                @if(!$configuracoes->contains('chave', 'limite_valor_total_servidor'))
                                    <tr>
                                        <td><code>limite_valor_total_servidor</code></td>
                                        <td><span class="text-muted small">Teto de valor total por servidor em um mês</span></td>
                                        <td><input type="number" step="0.01" class="form-control" name="limite_valor_total_servidor" value=""></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-1"></i> Salvar Configurações
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning shadow-sm border-0">
    <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Atenção: Impacto Sistêmico</h6>
    <p class="mb-0 small">
        Alterações nos parâmetros acima afetam as regras de validação para <strong>todos os novos lançamentos setoriais</strong>. 
        Lançamentos já criados ou exportados não terão seus valores recalculados automaticamente. Verifique com a diretoria antes de ajustar limites financeiros ou orçamentários.
    </p>
</div>
@endsection
