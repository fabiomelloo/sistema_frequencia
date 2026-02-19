@extends('layouts.app')

@section('title', 'Novo Evento')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="bi bi-calendar-event"></i> Criar Novo Evento</h1>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Erros encontrados:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.eventos.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="codigo_evento" class="form-label">Código do Evento <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('codigo_evento') is-invalid @enderror" 
                               id="codigo_evento" name="codigo_evento" value="{{ old('codigo_evento') }}" 
                               required maxlength="10">
                        <div class="form-text">Máximo 10 caracteres (compatível com exportação TXT).</div>
                        @error('codigo_evento')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tipo_evento" class="form-label">Tipo de Evento <span class="text-danger">*</span></label>
                        <select class="form-control @error('tipo_evento') is-invalid @enderror" 
                                id="tipo_evento" name="tipo_evento" required>
                            <option value="">-- Selecione --</option>
                            @foreach(\App\Enums\TipoEvento::cases() as $tipo)
                                <option value="{{ $tipo->value }}" @selected(old('tipo_evento') == $tipo->value)>
                                    {{ $tipo->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="tipo-helper"></div>
                        @error('tipo_evento')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('descricao') is-invalid @enderror" 
                               id="descricao" name="descricao" value="{{ old('descricao') }}" required>
                        @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_dias" name="exige_dias" {{ old('exige_dias') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_dias">Exige Dias</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_valor" name="exige_valor" {{ old('exige_valor') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_valor">Exige Valor</label>
                        </div>
                    </div>
                </div>

                <div class="row" id="limites-valor-container" style="display: none;">
                    <div class="col-md-4 mb-3">
                        <label for="valor_minimo" class="form-label">Valor Mínimo <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control @error('valor_minimo') is-invalid @enderror" 
                               id="valor_minimo" name="valor_minimo" value="{{ old('valor_minimo') }}">
                        @error('valor_minimo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="valor_maximo" class="form-label">Valor Máximo <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control @error('valor_maximo') is-invalid @enderror" 
                               id="valor_maximo" name="valor_maximo" value="{{ old('valor_maximo') }}">
                        @error('valor_maximo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-text mt-4 text-muted">
                            <i class="bi bi-info-circle"></i> Defina os limites de valor que serão validados nos lançamentos.
                        </div>
                    </div>
                </div>

                <div class="row" id="limite-dias-container" style="display: none;">
                    <div class="col-md-4 mb-3">
                        <label for="dias_maximo" class="form-label">Dias Máximo</label>
                        <input type="number" class="form-control @error('dias_maximo') is-invalid @enderror" 
                               id="dias_maximo" name="dias_maximo" value="{{ old('dias_maximo') }}" min="1" max="31">
                        <div class="form-text">Limite de dias por lançamento (1–31).</div>
                        @error('dias_maximo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_observacao" name="exige_observacao" {{ old('exige_observacao') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_observacao">Exige Observação</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="exige_porcentagem" name="exige_porcentagem" {{ old('exige_porcentagem') ? 'checked' : '' }}>
                            <label class="form-check-label" for="exige_porcentagem">Exige Porcentagem (Insalubridade/Periculosidade)</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="ativo" name="ativo" {{ old('ativo', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Criar Evento
                    </button>
                    <a href="{{ route('admin.eventos.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const exigeDias = document.getElementById('exige_dias');
    const exigeValor = document.getElementById('exige_valor');
    const tipoEvento = document.getElementById('tipo_evento');
    const tipoHelper = document.getElementById('tipo-helper');
    const limitesValor = document.getElementById('limites-valor-container');
    const limiteDias = document.getElementById('limite-dias-container');

    const helperTexts = {
        'ADICIONAL_TURNO': 'Adicional para servidores com função de vigia. Exige dias trabalhados.',
        'ADICIONAL_NOTURNO': 'Adicional para trabalho noturno. Exige dias noturnos.',
        'INSALUBRIDADE': 'Percentuais válidos: 10%, 20% ou 40%. Não acumula com periculosidade.',
        'PERICULOSIDADE': 'Percentual fixo: 30%. Não acumula com insalubridade.',
        'GRATIFICACAO': 'Exige valor de gratificação ou porcentagem (não ambos).',
        'FREQUENCIA': 'Evento de frequência padrão. Exige dias trabalhados.',
        'OUTROS': 'Evento personalizado — configure as exigências abaixo.'
    };

    function updateUI() {
        // Mostrar/esconder campos condicionais
        limitesValor.style.display = exigeValor.checked ? '' : 'none';
        limiteDias.style.display = exigeDias.checked ? '' : 'none';

        // Helper text do tipo
        const tipo = tipoEvento.value;
        tipoHelper.textContent = helperTexts[tipo] || '';
    }

    exigeDias.addEventListener('change', updateUI);
    exigeValor.addEventListener('change', updateUI);
    tipoEvento.addEventListener('change', updateUI);
    updateUI();
});
</script>
@endsection
