@extends('layouts.app')

@section('title', 'Histórico Funcional')

@section('content')
<div class="container py-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-clock-history" aria-hidden="true"></i> Histórico funcional</h1>
            <p class="text-muted mb-0">{{ $servidor->nome }} · Matrícula {{ $servidor->matricula }}</p>
        </div>
        <a href="{{ route('admin.servidores.show', $servidor) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao servidor
        </a>
    </div>

    @error('vigencia')
        <div class="alert alert-danger" role="alert"><strong>Vigência não registrada:</strong> {{ $message }}</div>
    @enderror

    <div class="alert alert-info" role="status">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        Alterações não apagam o passado. Uma nova situação funcional encerra a anterior no dia precedente.
    </div>

    <section class="card mb-4" aria-labelledby="titulo-vinculos">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
                <h2 id="titulo-vinculos" class="h5 mb-1">Vínculos, cargos e carga horária</h2>
                <p class="small text-muted mb-0">Representa os campos SIT, CARGO e CHs sem sobrescrever competências anteriores.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Vigência</th><th>Vínculo</th><th>Cargo</th><th>Carga</th><th>Documento</th></tr></thead>
                    <tbody>
                        @forelse ($servidor->vinculosFuncionais as $vinculo)
                            <tr>
                                <td>{{ $vinculo->data_inicio?->format('d/m/Y') ?? 'Início não informado' }} — {{ $vinculo->data_fim?->format('d/m/Y') ?? 'Atual' }}</td>
                                <td>{{ $vinculo->tipo_vinculo?->label() ?? 'Não informado' }}</td>
                                <td>{{ $vinculo->cargo ?? 'Não informado' }}</td>
                                <td>{{ $vinculo->carga_horaria ? $vinculo->carga_horaria.'h' : '—' }}</td>
                                <td>{{ $vinculo->ato_referencia ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">Nenhum histórico disponível.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('admin.servidores.historico.vinculos.store', $servidor) }}" class="border rounded p-3">
                @csrf
                <h3 class="h6 mb-3">Registrar nova situação vigente</h3>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo_vinculo" class="form-label">Vínculo <span class="text-danger">*</span></label>
                        <select id="tipo_vinculo" name="tipo_vinculo" class="form-select @error('tipo_vinculo') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach ($tiposVinculo as $tipo)
                                <option value="{{ $tipo->value }}" @selected(old('tipo_vinculo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                        @error('tipo_vinculo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="cargo" class="form-label">Cargo <span class="text-danger">*</span></label>
                        <input id="cargo" name="cargo" value="{{ old('cargo') }}" class="form-control @error('cargo') is-invalid @enderror" maxlength="150" required>
                        @error('cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label for="carga_horaria" class="form-label">Horas/semana <span class="text-danger">*</span></label>
                        <input type="number" id="carga_horaria" name="carga_horaria" value="{{ old('carga_horaria') }}" class="form-control @error('carga_horaria') is-invalid @enderror" min="1" max="80" required>
                        @error('carga_horaria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="data_inicio_vinculo" class="form-label">Início <span class="text-danger">*</span></label>
                        <input type="date" id="data_inicio_vinculo" name="data_inicio" value="{{ old('data_inicio') }}" max="{{ now()->toDateString() }}" class="form-control @error('data_inicio') is-invalid @enderror" required>
                        @error('data_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="ato_referencia_vinculo" class="form-label">Ato ou documento</label>
                        <input id="ato_referencia_vinculo" name="ato_referencia" value="{{ old('ato_referencia') }}" class="form-control @error('ato_referencia') is-invalid @enderror" maxlength="255">
                        @error('ato_referencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="observacao_vinculo" class="form-label">Observação</label>
                        <input id="observacao_vinculo" name="observacao" value="{{ old('observacao') }}" class="form-control" maxlength="1000">
                    </div>
                </div>
                <div class="text-end mt-3"><button class="btn btn-primary" type="submit">Registrar nova vigência</button></div>
            </form>
        </div>
    </section>

    <section class="card mb-4" aria-labelledby="titulo-designacoes">
        <div class="card-header">
            <h2 id="titulo-designacoes" class="h5 mb-1">Funções e designações</h2>
            <p class="small text-muted mb-0">DAS, FGT, FCT, GRT e outras designações ficam separadas do vínculo.</p>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                @forelse ($servidor->designacoesFuncionais as $designacao)
                    <div class="col-lg-6">
                        <article class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <strong>{{ $designacao->tipo->value }}{{ $designacao->nivel ? '-'.$designacao->nivel : '' }}</strong>
                                <span class="badge {{ $designacao->data_fim ? 'bg-secondary' : 'bg-success' }}">{{ $designacao->data_fim ? 'Encerrada' : 'Vigente' }}</span>
                            </div>
                            <p class="mb-1">{{ $designacao->descricao }}</p>
                            <p class="small text-muted mb-2">{{ $designacao->data_inicio->format('d/m/Y') }} — {{ $designacao->data_fim?->format('d/m/Y') ?? 'atual' }}</p>
                            @if (! $designacao->data_fim)
                                <form method="POST" action="{{ route('admin.servidores.historico.designacoes.encerrar', [$servidor, $designacao]) }}" class="d-flex flex-column flex-sm-row gap-2">
                                    @csrf @method('PATCH')
                                    <label for="fim_designacao_{{ $designacao->id }}" class="visually-hidden">Data final da designação</label>
                                    <input type="date" id="fim_designacao_{{ $designacao->id }}" name="data_fim" min="{{ $designacao->data_inicio->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required>
                                    <button class="btn btn-outline-danger" type="submit">Encerrar</button>
                                </form>
                            @endif
                        </article>
                    </div>
                @empty
                    <div class="col-12"><p class="text-muted mb-0">Nenhuma designação registrada.</p></div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.servidores.historico.designacoes.store', $servidor) }}" class="border rounded p-3">
                @csrf
                <h3 class="h6 mb-3">Adicionar designação</h3>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo_designacao" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select id="tipo_designacao" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach ($tiposDesignacao as $tipo)
                                <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label for="nivel_designacao" class="form-label">Nível</label>
                        <input id="nivel_designacao" name="nivel" value="{{ old('nivel') }}" class="form-control @error('nivel') is-invalid @enderror" maxlength="30">
                        @error('nivel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="descricao_designacao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input id="descricao_designacao" name="descricao" value="{{ old('descricao') }}" class="form-control @error('descricao') is-invalid @enderror" maxlength="150" required>
                        @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="inicio_designacao" class="form-label">Início <span class="text-danger">*</span></label>
                        <input type="date" id="inicio_designacao" name="data_inicio" value="{{ old('data_inicio') }}" max="{{ now()->toDateString() }}" class="form-control @error('data_inicio') is-invalid @enderror" required>
                    </div>
                    <div class="col-md-3">
                        <label for="fim_designacao" class="form-label">Fim, se conhecido</label>
                        <input type="date" id="fim_designacao" name="data_fim" value="{{ old('data_fim') }}" class="form-control @error('data_fim') is-invalid @enderror">
                        @error('data_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="ato_designacao" class="form-label">Ato ou documento</label>
                        <input id="ato_designacao" name="ato_referencia" value="{{ old('ato_referencia') }}" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-5">
                        <label for="obs_designacao" class="form-label">Observação</label>
                        <input id="obs_designacao" name="observacao" value="{{ old('observacao') }}" class="form-control" maxlength="1000">
                    </div>
                </div>
                <div class="text-end mt-3"><button class="btn btn-primary" type="submit">Adicionar designação</button></div>
            </form>
        </div>
    </section>

    <section class="card" aria-labelledby="titulo-vantagens">
        <div class="card-header">
            <h2 id="titulo-vantagens" class="h5 mb-1">Vantagens funcionais</h2>
            <p class="small text-muted mb-0">Somente regras validadas no catálogo e marcadas como cadastro funcional podem ser usadas.</p>
        </div>
        <div class="card-body">
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Item</th><th>Conteúdo</th><th>Vigência</th><th>Documento</th><th><span class="visually-hidden">Ações</span></th></tr></thead>
                    <tbody>
                        @forelse ($servidor->vantagensFuncionais as $vantagem)
                            <tr>
                                <td>{{ $vantagem->evento->sigla ?? $vantagem->evento->codigo_evento }} — {{ $vantagem->evento->descricao }}</td>
                                <td>{{ $vantagem->percentual !== null ? $vantagem->percentual.'%' : ($vantagem->valor !== null ? 'R$ '.$vantagem->valor : ($vantagem->quantidade ?? $vantagem->nivel ?? $vantagem->texto ?? 'Marcador')) }}</td>
                                <td>{{ $vantagem->data_inicio->format('d/m/Y') }} — {{ $vantagem->data_fim?->format('d/m/Y') ?? 'atual' }}</td>
                                <td>{{ $vantagem->referencia_documento ?? '—' }}</td>
                                <td class="text-end">
                                    @if (! $vantagem->data_fim)
                                        <form method="POST" action="{{ route('admin.servidores.historico.vantagens.encerrar', [$servidor, $vantagem]) }}" class="d-flex gap-2 justify-content-end">
                                            @csrf @method('PATCH')
                                            <label for="fim_vantagem_{{ $vantagem->id }}" class="visually-hidden">Data final da vantagem</label>
                                            <input type="date" id="fim_vantagem_{{ $vantagem->id }}" name="data_fim" min="{{ $vantagem->data_inicio->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Encerrar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">Nenhuma vantagem funcional registrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($eventos->isEmpty())
                <div class="alert alert-warning mb-0" role="status">Ainda não há item de cadastro funcional validado no catálogo. A vantagem não pode ser criada até a regra ser homologada.</div>
            @else
                <form method="POST" action="{{ route('admin.servidores.historico.vantagens.store', $servidor) }}" class="border rounded p-3" id="form-vantagem">
                    @csrf
                    <h3 class="h6 mb-3">Adicionar vantagem</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="evento_id" class="form-label">Item validado <span class="text-danger">*</span></label>
                            <select id="evento_id" name="evento_id" class="form-select @error('evento_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($eventos as $evento)
                                    <option value="{{ $evento->id }}" data-unidade="{{ $evento->unidade_lancamento->value }}" @selected((string) old('evento_id') === (string) $evento->id)>{{ $evento->sigla ?? $evento->codigo_evento }} — {{ $evento->descricao }} ({{ $evento->unidade_lancamento->label() }})</option>
                                @endforeach
                            </select>
                            @error('evento_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @foreach ([['percentual', 'Percentual', 'number'], ['valor', 'Valor', 'number'], ['quantidade', 'Quantidade', 'number'], ['nivel', 'Nível', 'text'], ['texto', 'Texto', 'text']] as [$campo, $rotulo, $tipo])
                            <div class="col-md-3 campo-vantagem" data-campo="{{ $campo }}">
                                <label for="{{ $campo }}" class="form-label">{{ $rotulo }}</label>
                                <input type="{{ $tipo }}" id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo) }}" class="form-control @error($campo) is-invalid @enderror" @if ($tipo === 'number') step="0.01" min="0" @endif>
                                @error($campo)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="col-md-3">
                            <label for="inicio_vantagem" class="form-label">Início <span class="text-danger">*</span></label>
                            <input type="date" id="inicio_vantagem" name="data_inicio" value="{{ old('data_inicio') }}" max="{{ now()->toDateString() }}" class="form-control @error('data_inicio') is-invalid @enderror" required>
                        </div>
                        <div class="col-md-3">
                            <label for="fim_vantagem" class="form-label">Fim, se conhecido</label>
                            <input type="date" id="fim_vantagem" name="data_fim" value="{{ old('data_fim') }}" class="form-control @error('data_fim') is-invalid @enderror">
                        </div>
                        <div class="col-md-6">
                            <label for="referencia_documento" class="form-label">Referência do documento</label>
                            <input id="referencia_documento" name="referencia_documento" value="{{ old('referencia_documento') }}" class="form-control @error('referencia_documento') is-invalid @enderror" maxlength="255">
                            @error('referencia_documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="observacao_vantagem" class="form-label">Observação</label>
                            <textarea id="observacao_vantagem" name="observacao" class="form-control" rows="2" maxlength="1000">{{ old('observacao') }}</textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3"><button class="btn btn-primary" type="submit">Adicionar vantagem</button></div>
                </form>
            @endif
        </div>
    </section>
</div>

@if ($eventos->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const seletor = document.getElementById('evento_id');
    const campos = document.querySelectorAll('.campo-vantagem');
    const mapa = { PERCENTUAL: 'percentual', VALOR: 'valor', DIAS: 'quantidade', HORAS: 'quantidade', NIVEL: 'nivel', TEXTO: 'texto' };
    function atualizarCampos() {
        const unidade = seletor.options[seletor.selectedIndex]?.dataset.unidade;
        const campoAtivo = mapa[unidade];
        campos.forEach(function (container) {
            const ativo = container.dataset.campo === campoAtivo;
            container.hidden = !ativo;
            container.querySelector('input').disabled = !ativo;
        });
    }
    seletor.addEventListener('change', atualizarCampos);
    atualizarCampos();
});
</script>
@endif
@endsection
