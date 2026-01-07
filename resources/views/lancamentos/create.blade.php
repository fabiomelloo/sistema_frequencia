@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Novo Lançamento Setorial</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('lancamentos.index') }}" class="btn btn-secondary">Voltar</a>
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
            <form action="{{ route('lancamentos.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="servidor_id" class="form-label">Matrícula do Servidor <span class="text-danger">*</span></label>
                        <select name="servidor_id" id="servidor_id" class="form-control @error('servidor_id') is-invalid @enderror" required>
                            <option value="">-- Selecione --</option>
                            @foreach ($servidores as $servidor)
                                <option value="{{ $servidor->id }}" @selected(old('servidor_id') == $servidor->id)>
                                    {{ $servidor->matricula }} - {{ $servidor->nome }}
                                </option>
                            @endforeach
                        </select>
                        @error('servidor_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="evento_id" class="form-label">Evento <span class="text-danger">*</span></label>
                        <select name="evento_id" id="evento_id" class="form-control @error('evento_id') is-invalid @enderror" required>
                            <option value="">-- Selecione --</option>
                            @foreach ($eventos as $evento)
                                <option value="{{ $evento->id }}" data-exige-dias="{{ $evento->exige_dias ? 1 : 0 }}" data-exige-valor="{{ $evento->exige_valor ? 1 : 0 }}" data-exige-observacao="{{ $evento->exige_observacao ? 1 : 0 }}" data-exige-porcentagem="{{ $evento->exige_porcentagem ? 1 : 0 }}" @selected(old('evento_id') == $evento->id)>
                                    {{ $evento->codigo_evento }} - {{ $evento->descricao }}
                                </option>
                            @endforeach
                        </select>
                        @error('evento_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3" id="dias-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="dias_trabalhados" class="form-label">Dias Trabalhados</label>
                        <input type="number" name="dias_trabalhados" id="dias_trabalhados" class="form-control @error('dias_trabalhados') is-invalid @enderror" min="0" value="{{ old('dias_trabalhados') }}">
                        @error('dias_trabalhados')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3" id="valor-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="number" name="valor" id="valor" class="form-control @error('valor') is-invalid @enderror" step="0.01" min="0" value="{{ old('valor') }}">
                        @error('valor')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3" id="porcentagem-container" style="display: none;">
                    <div class="col-md-6">
                        <label for="porcentagem_insalubridade" class="form-label">Porcentagem de Insalubridade (%) <span class="text-danger">*</span></label>
                        <select name="porcentagem_insalubridade" id="porcentagem_insalubridade" class="form-control @error('porcentagem_insalubridade') is-invalid @enderror">
                            <option value="">-- Selecione --</option>
                            <option value="10" @selected(old('porcentagem_insalubridade') == 10)>10%</option>
                            <option value="20" @selected(old('porcentagem_insalubridade') == 20)>20%</option>
                            <option value="40" @selected(old('porcentagem_insalubridade') == 40)>40%</option>
                        </select>
                        @error('porcentagem_insalubridade')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3" id="observacao-container" style="display: none;">
                    <div class="col-md-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea name="observacao" id="observacao" class="form-control @error('observacao') is-invalid @enderror" rows="3">{{ old('observacao') }}</textarea>
                        @error('observacao')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Enviar Lançamento</button>
                        <a href="{{ route('lancamentos.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventoSelect = document.getElementById('evento_id');
    
    function showRequiredFields() {
        const selected = eventoSelect.options[eventoSelect.selectedIndex];
        const exigeDias = selected.dataset.exigeDias === '1';
        const exigeValor = selected.dataset.exigeValor === '1';
        const exigeObservacao = selected.dataset.exigeObservacao === '1';
        const exigePorcentagem = selected.dataset.exigePorcentagem === '1';
        
        document.getElementById('dias-container').style.display = exigeDias ? 'block' : 'none';
        document.getElementById('valor-container').style.display = exigeValor ? 'block' : 'none';
        document.getElementById('porcentagem-container').style.display = exigePorcentagem ? 'block' : 'none';
        document.getElementById('observacao-container').style.display = exigeObservacao ? 'block' : 'none';
        
        if (exigeDias) document.getElementById('dias_trabalhados').required = true;
        else document.getElementById('dias_trabalhados').required = false;
        
        if (exigeValor) document.getElementById('valor').required = true;
        else document.getElementById('valor').required = false;
        
        if (exigePorcentagem) document.getElementById('porcentagem_insalubridade').required = true;
        else document.getElementById('porcentagem_insalubridade').required = false;
    }
    
    eventoSelect.addEventListener('change', showRequiredFields);
    showRequiredFields();
});
</script>
@endsection
