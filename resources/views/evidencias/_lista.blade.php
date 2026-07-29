@php
    $podeRemover = $podeRemover ?? false;
    $contextoRevisao = $contextoRevisao ?? false;
@endphp

<div class="d-flex flex-column gap-2">
    @forelse ($evidencias as $evidencia)
        @php
            $errosRevisao = $errors->getBag('revisao_evidencia_'.$evidencia->id);
            $revisaoComErro = $errosRevisao->any();
        @endphp
        <div class="border rounded p-3">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                <div class="min-w-0">
                    <div class="fw-semibold text-break"><i class="bi bi-file-earmark-lock me-1" aria-hidden="true"></i>{{ $evidencia->nome_original }}</div>
                    <div class="small text-muted">{{ $evidencia->tamanhoFormatado() }} · enviado por {{ $evidencia->enviadoPor?->name ?? 'usuário não disponível' }} em {{ $evidencia->created_at->format('d/m/Y H:i') }}</div>
                    @if ($evidencia->descricao)<div class="small mt-1 text-break">{{ $evidencia->descricao }}</div>@endif
                </div>
                <span class="badge text-bg-{{ $evidencia->status->cor() }} align-self-start">{{ $evidencia->status->label() }}</span>
            </div>

            @if ($evidencia->motivo_revisao)
                <div class="alert alert-danger py-2 mt-2 mb-0" role="alert">
                    <strong>Motivo:</strong> {{ $evidencia->motivo_revisao }}
                    @if ($evidencia->revisadoPor)<div class="small mt-1">Por {{ $evidencia->revisadoPor->name }} em {{ $evidencia->revisado_em?->format('d/m/Y H:i') }}.</div>@endif
                </div>
            @elseif ($evidencia->revisadoPor)
                <div class="small text-success mt-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Revisado por {{ $evidencia->revisadoPor->name }} em {{ $evidencia->revisado_em?->format('d/m/Y H:i') }}.</div>
            @endif

            <div class="d-flex flex-wrap gap-2 mt-2">
                @can('view', $evidencia)
                    <a href="{{ route('evidencias.download', $evidencia) }}" class="btn btn-outline-secondary"><i class="bi bi-download me-1" aria-hidden="true"></i>Baixar documento</a>
                @endcan
                @if ($podeRemover)
                    @can('delete', $evidencia)
                        <form method="POST" action="{{ route('evidencias.destroy', $evidencia) }}" onsubmit="return confirm('Remover este documento do armazenamento privado?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Remover documento</button>
                        </form>
                    @endcan
                @endif
            </div>

            @if ($contextoRevisao && $folha->estaFinalizada())
                <form method="POST" action="{{ route('painel-frequencias.evidencias.revisar', [$folha, $item, $evidencia]) }}" class="border-top mt-3 pt-3">
                    @csrf
                    @if ($errosRevisao->any())
                        <div class="alert alert-danger py-2" role="alert">{{ $errosRevisao->first() }}</div>
                    @endif
                    <div class="row g-2 align-items-end">
                        <div class="col-12">
                            <label for="status-evidencia-{{ $evidencia->id }}" class="form-label">Análise <span class="text-danger">*</span></label>
                            <select id="status-evidencia-{{ $evidencia->id }}" name="status" class="form-select @if($errosRevisao->has('status')) is-invalid @endif" required>
                                <option value="">Selecione...</option>
                                @foreach ($statusEvidencia as $statusDocumento)
                                    <option value="{{ $statusDocumento->value }}" @selected(($revisaoComErro ? old('status') : $evidencia->status->value) === $statusDocumento->value)>{{ $statusDocumento->label() }}</option>
                                @endforeach
                            </select>
                            @if ($errosRevisao->has('status'))<div class="invalid-feedback">{{ $errosRevisao->first('status') }}</div>@endif
                        </div>
                        <div class="col-12">
                            <label for="motivo-evidencia-{{ $evidencia->id }}" class="form-label">Motivo da recusa</label>
                            <textarea id="motivo-evidencia-{{ $evidencia->id }}" name="motivo_revisao" rows="2" minlength="10" maxlength="2000" class="form-control @if($errosRevisao->has('motivo_revisao')) is-invalid @endif" aria-describedby="ajuda-motivo-evidencia-{{ $evidencia->id }}">{{ $revisaoComErro ? old('motivo_revisao') : $evidencia->motivo_revisao }}</textarea>
                            @if ($errosRevisao->has('motivo_revisao'))<div class="invalid-feedback">{{ $errosRevisao->first('motivo_revisao') }}</div>@endif
                            <div id="ajuda-motivo-evidencia-{{ $evidencia->id }}" class="form-text">Obrigatório somente quando o documento for recusado.</div>
                        </div>
                        <div class="col-12 d-grid d-md-flex justify-content-md-end"><button type="submit" class="btn btn-primary">Salvar análise</button></div>
                    </div>
                </form>
            @endif
        </div>
    @empty
        <div class="text-muted small">Nenhum documento comprobatório anexado.</div>
    @endforelse
</div>
