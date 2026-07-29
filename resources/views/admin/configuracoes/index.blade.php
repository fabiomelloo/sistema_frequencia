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
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $erro)
                                <li>{{ $erro }}</li>
                            @endforeach
                        </ul>
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
                                            <code>{{ $config['chave'] }}</code>
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $config['descricao'] }}</span>
                                        </td>
                                        <td>
                                            @if($config['tipo'] === 'boolean')
                                                <select class="form-select @error($config['chave']) is-invalid @enderror"
                                                        name="{{ $config['chave'] }}">
                                                    <option value="false" @selected(old($config['chave'], $config['valor']) === 'false')>Não</option>
                                                    <option value="true" @selected(old($config['chave'], $config['valor']) === 'true')>Sim</option>
                                                </select>
                                            @else
                                                <input type="{{ $config['tipo'] }}"
                                                       class="form-control @error($config['chave']) is-invalid @enderror"
                                                       name="{{ $config['chave'] }}"
                                                       value="{{ old($config['chave'], $config['valor']) }}"
                                                       @if($config['step']) step="{{ $config['step'] }}" @endif>
                                            @endif
                                            @error($config['chave'])
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            Nenhuma configuração encontrada no banco de dados.
                                        </td>
                                    </tr>
                                @endforelse
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
