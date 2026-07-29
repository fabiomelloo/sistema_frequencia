@php
    $diasInformados = old('dias_especificos', isset($ocorrencia) ? $ocorrencia->dias->pluck('data')->map->format('Y-m-d')->all() : ['']);
    $diasInformados = count($diasInformados) ? $diasInformados : [''];
@endphp

@if ($retorno ?? null)
    <input type="hidden" name="retorno" value="{{ old('retorno', $retorno) }}">
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Revise os campos destacados.</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-lg-7">
                <label for="servidor_id" class="form-label fw-semibold">Servidor <span class="text-danger">*</span></label>
                <select id="servidor_id" name="servidor_id" class="form-select @error('servidor_id') is-invalid @enderror" required>
                    <option value="">Selecione</option>
                    @foreach ($servidores as $servidor)
                        <option value="{{ $servidor->id }}" @selected(old('servidor_id', $ocorrencia->servidor_id ?? request('servidor_id')) == $servidor->id)>
                            {{ $servidor->nome }} — Matrícula {{ $servidor->matricula }}
                        </option>
                    @endforeach
                </select>
                @error('servidor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-5">
                <label for="competencia_id" class="form-label fw-semibold">Competência <span class="text-danger">*</span></label>
                <select id="competencia_id" name="competencia_id" class="form-select @error('competencia_id') is-invalid @enderror" required>
                    <option value="">Selecione</option>
                    @foreach ($competencias as $competencia)
                        <option value="{{ $competencia->id }}" @selected(old('competencia_id', $ocorrencia->competencia_id ?? request('competencia_id')) == $competencia->id)>
                            {{ $competencia->descricao }}
                        </option>
                    @endforeach
                </select>
                @error('competencia_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">As datas devem estar dentro do período exibido.</div>
            </div>

            <div class="col-lg-6">
                <label for="tipo" class="form-label fw-semibold">Tipo da ocorrência <span class="text-danger">*</span></label>
                <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                    <option value="">Selecione</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->value }}" @selected(old('tipo', isset($ocorrencia) ? $ocorrencia->tipo->value : null) === $tipo->value)>{{ $tipo->label() }}</option>
                    @endforeach
                </select>
                @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="justificada" class="form-label fw-semibold">Justificação</label>
                <select id="justificada" name="justificada" class="form-select @error('justificada') is-invalid @enderror">
                    <option value="">Não informada</option>
                    <option value="1" @selected(old('justificada', $ocorrencia->justificada ?? null) === true || old('justificada') === '1')>Justificada</option>
                    <option value="0" @selected(old('justificada', isset($ocorrencia) && $ocorrencia->justificada === false ? '0' : null) === '0')>Não justificada</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="hidden" name="possui_comprovacao" value="0">
                    <input class="form-check-input" type="checkbox" id="possui_comprovacao" name="possui_comprovacao" value="1" @checked(old('possui_comprovacao', $ocorrencia->possui_comprovacao ?? false))>
                    <label class="form-check-label" for="possui_comprovacao">Possui comprovação</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h2 class="h6 fw-bold mb-0">Quando ocorreu</h2>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small">Informe um período contínuo ou adicione dias separados, conforme consta no documento original.</p>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="data_inicio" class="form-label">Início do período</label>
                <input type="date" id="data_inicio" name="data_inicio" value="{{ old('data_inicio', isset($ocorrencia) ? $ocorrencia->data_inicio?->format('Y-m-d') : '') }}" class="form-control @error('data_inicio') is-invalid @enderror">
                @error('data_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="data_fim" class="form-label">Fim do período</label>
                <input type="date" id="data_fim" name="data_fim" value="{{ old('data_fim', isset($ocorrencia) ? $ocorrencia->data_fim?->format('Y-m-d') : '') }}" class="form-control @error('data_fim') is-invalid @enderror">
                @error('data_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-semibold mb-0">Dias específicos</label>
            <button type="button" id="adicionar-dia" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Adicionar dia
            </button>
        </div>
        <div id="lista-dias" class="row g-2">
            @foreach ($diasInformados as $dia)
                <div class="col-sm-6 col-lg-4 dia-item">
                    <div class="input-group">
                        <input type="date" name="dias_especificos[]" value="{{ $dia }}" class="form-control @error('dias_especificos.*') is-invalid @enderror" aria-label="Dia específico">
                        <button type="button" class="btn btn-outline-danger remover-dia" aria-label="Remover dia"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            @endforeach
        </div>
        @error('dias_especificos.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <label for="referencia_documento" class="form-label fw-semibold">Referência da comprovação</label>
            <input type="text" id="referencia_documento" name="referencia_documento" maxlength="255" value="{{ old('referencia_documento', $ocorrencia->referencia_documento ?? '') }}" class="form-control @error('referencia_documento') is-invalid @enderror" placeholder="Ex.: atestado entregue ao RH em 15/07">
            <div class="form-text">Informe a referência; após salvar a ocorrência, anexe o arquivo comprobatório na tela de detalhes.</div>
        </div>
        <div>
            <label for="observacao_original" class="form-label fw-semibold">Observação original</label>
            <textarea id="observacao_original" name="observacao_original" rows="4" maxlength="2000" class="form-control @error('observacao_original') is-invalid @enderror" placeholder="Transcreva a informação relevante do documento de origem.">{{ old('observacao_original', $ocorrencia->observacao_original ?? '') }}</textarea>
            @error('observacao_original')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-end">
    <a href="{{ ($retorno ?? null) ?: (isset($ocorrencia) ? route('ocorrencias.show', $ocorrencia) : route('ocorrencias.index')) }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>{{ $textoBotao }}</button>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lista = document.getElementById('lista-dias');
    document.getElementById('adicionar-dia').addEventListener('click', () => {
        const item = document.createElement('div');
        item.className = 'col-sm-6 col-lg-4 dia-item';
        item.innerHTML = '<div class="input-group"><input type="date" name="dias_especificos[]" class="form-control" aria-label="Dia específico"><button type="button" class="btn btn-outline-danger remover-dia" aria-label="Remover dia"><i class="bi bi-x-lg"></i></button></div>';
        lista.appendChild(item);
        item.querySelector('input').focus();
    });
    lista.addEventListener('click', (event) => {
        const botao = event.target.closest('.remover-dia');
        if (botao) botao.closest('.dia-item').remove();
    });
});
</script>
@endsection
