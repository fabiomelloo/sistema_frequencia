@extends('layouts.app')

@section('title', 'Novo Lançamento — Sistema de Frequência')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2"></i>Novo Lançamento Setorial</h4>
    <a href="{{ route('lancamentos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Erros encontrados:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4 p-md-5">
        <form action="{{ route('lancamentos.store') }}" method="POST" id="formLancamento" class="needs-validation">
            @csrf

            <div class="row g-3 mb-3">
                {{-- Competência --}}
                <div class="col-md-3">
                    <label for="competencia" class="form-label fw-semibold">Competência <span class="text-danger">*</span></label>
                    <input type="month" name="competencia" id="competencia" class="form-control @error('competencia') is-invalid @enderror" value="{{ old('competencia', $competenciaAtual) }}" required>
                    @error('competencia')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Servidor --}}
                <div class="col-md-5">
                    <label for="servidor_id" class="form-label fw-semibold">Servidor <span class="text-danger">*</span></label>
                    <select name="servidor_id" id="servidor_id" class="form-select @error('servidor_id') is-invalid @enderror" required>
                        <option value="">-- Selecione o servidor --</option>
                        @foreach ($servidores as $servidor)
                            <option value="{{ $servidor->id }}" @selected(old('servidor_id') == $servidor->id)>
                                {{ $servidor->matricula }} — {{ $servidor->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('servidor_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Evento --}}
                <div class="col-md-4">
                    <label for="evento_id" class="form-label fw-semibold">Evento <span class="text-danger">*</span></label>
                    <select name="evento_id" id="evento_id" class="form-select @error('evento_id') is-invalid @enderror" required>
                        <option value="">-- Selecione o evento --</option>
                        @foreach ($eventos as $evento)
                            <option value="{{ $evento->id }}"
                                data-exige-dias="{{ $evento->exige_dias ? 1 : 0 }}"
                                data-exige-valor="{{ $evento->exige_valor ? 1 : 0 }}"
                                data-exige-observacao="{{ $evento->exige_observacao ? 1 : 0 }}"
                                data-exige-porcentagem="{{ $evento->exige_porcentagem ? 1 : 0 }}"
                                data-tipo="{{ $evento->tipo_evento ?? '' }}"
                                @selected(old('evento_id') == $evento->id)>
                                {{ $evento->codigo_evento }} — {{ $evento->descricao }}
                            </option>
                        @endforeach
                    </select>
                    @error('evento_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Campos condicionais --}}
            <div class="row g-3 mb-3">
                <div class="col-md-3" id="dias-container" style="display: none;">
                    <label for="dias_trabalhados" class="form-label fw-semibold">Dias Trabalhados</label>
                    <input type="number" name="dias_trabalhados" id="dias_trabalhados" class="form-control @error('dias_trabalhados') is-invalid @enderror" min="0" max="31" value="{{ old('dias_trabalhados') }}">
                    @error('dias_trabalhados') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="dias-noturnos-container" style="display: none;">
                    <label for="dias_noturnos" class="form-label fw-semibold">Dias Noturnos</label>
                    <input type="number" name="dias_noturnos" id="dias_noturnos" class="form-control @error('dias_noturnos') is-invalid @enderror" min="0" max="31" value="{{ old('dias_noturnos') }}">
                    @error('dias_noturnos') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="valor-container" style="display: none;">
                    <label for="valor" class="form-label fw-semibold">Valor (R$)</label>
                    <input type="number" name="valor" id="valor" class="form-control @error('valor') is-invalid @enderror" step="0.01" min="0" value="{{ old('valor') }}">
                    @error('valor') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="valor-gratificacao-container" style="display: none;">
                    <label for="valor_gratificacao" class="form-label fw-semibold">Gratificação (R$)</label>
                    <input type="number" name="valor_gratificacao" id="valor_gratificacao" class="form-control @error('valor_gratificacao') is-invalid @enderror" step="0.01" min="0" value="{{ old('valor_gratificacao') }}">
                    @error('valor_gratificacao') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3" id="porcentagem-container" style="display: none;">
                    <label for="porcentagem_insalubridade" class="form-label fw-semibold">Insalubridade (%)</label>
                    <select name="porcentagem_insalubridade" id="porcentagem_insalubridade" class="form-select @error('porcentagem_insalubridade') is-invalid @enderror">
                        <option value="">-- Selecione --</option>
                        <option value="10" @selected(old('porcentagem_insalubridade') == 10)>10%</option>
                        <option value="20" @selected(old('porcentagem_insalubridade') == 20)>20%</option>
                        <option value="40" @selected(old('porcentagem_insalubridade') == 40)>40%</option>
                    </select>
                    @error('porcentagem_insalubridade') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="periculosidade-container" style="display: none;">
                    <label for="porcentagem_periculosidade" class="form-label fw-semibold">Periculosidade (%)</label>
                    <select name="porcentagem_periculosidade" id="porcentagem_periculosidade" class="form-select @error('porcentagem_periculosidade') is-invalid @enderror">
                        <option value="">-- Selecione --</option>
                        <option value="30" @selected(old('porcentagem_periculosidade') == 30)>30%</option>
                    </select>
                    @error('porcentagem_periculosidade') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="adicional-turno-container" style="display: none;">
                    <label for="adicional_turno" class="form-label fw-semibold">Adicional de Turno (R$)</label>
                    <input type="number" name="adicional_turno" id="adicional_turno" class="form-control @error('adicional_turno') is-invalid @enderror" step="0.01" min="0" value="{{ old('adicional_turno') }}">
                    @error('adicional_turno') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>

                <div class="col-md-3" id="adicional-noturno-container" style="display: none;">
                    <label for="adicional_noturno" class="form-label fw-semibold">Adicional Noturno (R$)</label>
                    <input type="number" name="adicional_noturno" id="adicional_noturno" class="form-control @error('adicional_noturno') is-invalid @enderror" step="0.01" min="0" value="{{ old('adicional_noturno') }}">
                    @error('adicional_noturno') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="row g-3 mb-4" id="observacao-container" style="display: none;">
                <div class="col-12">
                    <label for="observacao" class="form-label fw-semibold">Observação</label>
                    <textarea name="observacao" id="observacao" class="form-control @error('observacao') is-invalid @enderror" rows="3">{{ old('observacao') }}</textarea>
                    @error('observacao') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <button type="submit" class="btn btn-primary px-4 py-2" id="btnSubmit">
                    <span id="btnText"><i class="bi bi-send me-2"></i>Enviar Lançamento</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
                <a href="{{ route('lancamentos.index') }}" class="btn btn-light px-4 py-2 text-secondary fw-semibold">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventoSelect = document.getElementById('evento_id');

    function showFields() {
        const sel = eventoSelect.options[eventoSelect.selectedIndex];
        const tipo = (sel.dataset.tipo || '').toUpperCase();
        const exigeDias = sel.dataset.exigeDias === '1';
        const exigeValor = sel.dataset.exigeValor === '1';
        const exigeObs = sel.dataset.exigeObservacao === '1';
        const exigePorcentagem = sel.dataset.exigePorcentagem === '1';

        // Dias trabalhados
        toggle('dias-container', exigeDias);
        // Dias noturnos (para adicional noturno)
        toggle('dias-noturnos-container', tipo.includes('NOTURNO'));
        // Valor
        toggle('valor-container', exigeValor);
        // Gratificação
        toggle('valor-gratificacao-container', tipo.includes('GRATIFICACAO'));
        // Insalubridade
        toggle('porcentagem-container', tipo.includes('INSALUBRIDADE') || exigePorcentagem);
        // Periculosidade
        toggle('periculosidade-container', tipo.includes('PERICULOSIDADE'));
        // Adicional turno
        toggle('adicional-turno-container', tipo.includes('TURNO') && !tipo.includes('NOTURNO'));
        // Adicional noturno
        toggle('adicional-noturno-container', tipo.includes('NOTURNO'));
        // Observação
        toggle('observacao-container', exigeObs || true); // sempre mostrar observação
    }

    function toggle(id, show) {
        const el = document.getElementById(id);
        if (el) el.style.display = show ? '' : 'none';
        
        // Add subtle animation for showing inputs
        if (show && el) {
            el.style.animation = 'fadeIn 0.3s ease-out';
        }
    }

    eventoSelect.addEventListener('change', showFields);
    showFields();

    // Loading State no botão
    const form = document.getElementById('formLancamento');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', function(e) {
        if(form.checkValidity()) {
            btnSubmit.disabled = true;
            btnText.textContent = 'Enviando...';
            btnSpinner.classList.remove('d-none');
        }
    });
});
</script>
@endsection
