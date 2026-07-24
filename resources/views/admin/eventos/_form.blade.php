@php
    $editando = $evento?->exists ?? false;
    $valorAtual = fn (string $campo, mixed $padrao = null) => old($campo, $editando ? $evento->{$campo} : $padrao);
@endphp

<form method="POST" action="{{ $editando ? route('admin.eventos.update', $evento) : route('admin.eventos.store') }}" id="evento-form">
    @csrf
    @if ($editando) @method('PUT') @endif

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="identificacao-heading">
        <div class="card-body p-4">
            <h2 id="identificacao-heading" class="h6 fw-bold mb-3">Identificação do item</h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="codigo_evento" class="form-label fw-semibold">Código oficial <span class="text-danger">*</span></label>
                    <input type="text" id="codigo_evento" name="codigo_evento" maxlength="10" required value="{{ $valorAtual('codigo_evento') }}" class="form-control @error('codigo_evento') is-invalid @enderror">
                    <div class="form-text">Código utilizado na integração com a folha.</div>
                    @error('codigo_evento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label for="sigla" class="form-label fw-semibold">Sigla de exibição</label>
                    <input type="text" id="sigla" name="sigla" maxlength="20" value="{{ $valorAtual('sigla') }}" class="form-control @error('sigla') is-invalid @enderror" placeholder="Ex.: INS">
                    @error('sigla')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7">
                    <label for="descricao" class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                    <input type="text" id="descricao" name="descricao" maxlength="255" required value="{{ $valorAtual('descricao') }}" class="form-control @error('descricao') is-invalid @enderror">
                    @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="tipo_evento" class="form-label fw-semibold">Grupo do evento <span class="text-danger">*</span></label>
                    <select id="tipo_evento" name="tipo_evento" required class="form-select @error('tipo_evento') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach (\App\Enums\TipoEvento::cases() as $tipo)
                            <option value="{{ $tipo->value }}" @selected($valorAtual('tipo_evento') instanceof \App\Enums\TipoEvento ? $valorAtual('tipo_evento')->value === $tipo->value : $valorAtual('tipo_evento') === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                    @error('tipo_evento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" @checked($valorAtual('ativo', true))>
                        <label class="form-check-label fw-semibold" for="ativo">Disponível para uso</label>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="regra-heading">
        <div class="card-body p-4">
            <h2 id="regra-heading" class="h6 fw-bold mb-1">Regra do item mensal</h2>
            <p class="small text-muted mb-3">Defina de onde vem a informação e como ela será representada. Isso evita copiar colunas diferentes de cada planilha.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="unidade_lancamento" class="form-label fw-semibold">Forma de lançamento <span class="text-danger">*</span></label>
                    <select id="unidade_lancamento" name="unidade_lancamento" required class="form-select @error('unidade_lancamento') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach (\App\Enums\UnidadeLancamento::cases() as $unidade)
                            <option value="{{ $unidade->value }}" data-description="{{ $unidade->descricao() }}" @selected($valorAtual('unidade_lancamento') instanceof \App\Enums\UnidadeLancamento ? $valorAtual('unidade_lancamento')->value === $unidade->value : $valorAtual('unidade_lancamento') === $unidade->value)>{{ $unidade->label() }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" id="unidade-ajuda"></div>
                    @error('unidade_lancamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="origem_informacao" class="form-label fw-semibold">Origem da informação <span class="text-danger">*</span></label>
                    <select id="origem_informacao" name="origem_informacao" required class="form-select @error('origem_informacao') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach (\App\Enums\OrigemInformacaoItem::cases() as $origem)
                            <option value="{{ $origem->value }}" @selected($valorAtual('origem_informacao') instanceof \App\Enums\OrigemInformacaoItem ? $valorAtual('origem_informacao')->value === $origem->value : $valorAtual('origem_informacao', 'SETOR_MENSAL') === $origem->value)>{{ $origem->label() }}</option>
                        @endforeach
                    </select>
                    @error('origem_informacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="instrucoes_lancamento" class="form-label fw-semibold">Instrução oficial de lançamento</label>
                    <textarea id="instrucoes_lancamento" name="instrucoes_lancamento" rows="4" maxlength="2000" class="form-control @error('instrucoes_lancamento') is-invalid @enderror" placeholder="Descreva quem pode receber o item, como informar e quais documentos ou limites devem ser observados.">{{ $valorAtual('instrucoes_lancamento') }}</textarea>
                    <div class="form-text">Obrigatória para confirmar que a regra foi validada.</div>
                    @error('instrucoes_lancamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="gera_efeito_financeiro" name="gera_efeito_financeiro" value="1" @checked($valorAtual('gera_efeito_financeiro', true))>
                        <label class="form-check-label" for="gera_efeito_financeiro">Gera efeito financeiro</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="exige_documento" name="exige_documento" value="1" @checked($valorAtual('exige_documento', false))>
                        <label class="form-check-label" for="exige_documento">Exige documento comprobatório</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="regra_validada" name="regra_validada" value="1" @checked($valorAtual('regra_validada', false))>
                        <label class="form-check-label fw-semibold" for="regra_validada">Regra conferida e validada</label>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="exigencias-heading">
        <div class="card-body p-4">
            <h2 id="exigencias-heading" class="h6 fw-bold mb-3">Dados exigidos no lançamento</h2>
            <div class="row g-3">
                @foreach ([
                    'exige_dias' => 'Quantidade de dias',
                    'exige_valor' => 'Valor monetário',
                    'exige_porcentagem' => 'Percentual',
                    'exige_observacao' => 'Observação',
                ] as $campo => $label)
                    <div class="col-sm-6 col-lg-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="{{ $campo }}" name="{{ $campo }}" value="1" @checked($valorAtual($campo, false))>
                            <label class="form-check-label" for="{{ $campo }}">{{ $label }}</label>
                        </div>
                        @error($campo)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mt-1" id="limites-valor">
                <div class="col-md-4">
                    <label for="valor_minimo" class="form-label">Valor mínimo</label>
                    <input type="number" step="0.01" min="0" id="valor_minimo" name="valor_minimo" value="{{ $valorAtual('valor_minimo') }}" class="form-control @error('valor_minimo') is-invalid @enderror">
                    @error('valor_minimo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="valor_maximo" class="form-label">Valor máximo</label>
                    <input type="number" step="0.01" min="0" id="valor_maximo" name="valor_maximo" value="{{ $valorAtual('valor_maximo') }}" class="form-control @error('valor_maximo') is-invalid @enderror">
                    @error('valor_maximo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mt-1" id="limite-dias">
                <div class="col-md-4">
                    <label for="dias_maximo" class="form-label">Limite de dias</label>
                    <input type="number" min="1" max="31" id="dias_maximo" name="dias_maximo" value="{{ $valorAtual('dias_maximo') }}" class="form-control @error('dias_maximo') is-invalid @enderror">
                    @error('dias_maximo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i>{{ $editando ? 'Salvar alterações' : 'Criar item mensal' }}</button>
        <a href="{{ route('admin.eventos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const unidade = document.getElementById('unidade_lancamento');
    const exigeValor = document.getElementById('exige_valor');
    const exigeDias = document.getElementById('exige_dias');
    const limitesValor = document.getElementById('limites-valor');
    const limiteDias = document.getElementById('limite-dias');
    const ajuda = document.getElementById('unidade-ajuda');

    function atualizar() {
        limitesValor.hidden = !exigeValor.checked;
        limiteDias.hidden = !exigeDias.checked;
        ajuda.textContent = unidade.options[unidade.selectedIndex]?.dataset.description || '';
    }

    exigeValor.addEventListener('change', atualizar);
    exigeDias.addEventListener('change', atualizar);
    unidade.addEventListener('change', atualizar);
    atualizar();
});
</script>
