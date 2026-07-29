@php
    $itensFuncionais = $item->itens->where('editavel_pelo_setor', false);
    $itensMensais = $item->itens->where('editavel_pelo_setor', true);
    $idsLancados = $item->itens->pluck('evento_id')->filter();
    $eventosDisponiveis = $eventosMensais->whereNotIn('id', $idsLancados);
    $errosItem = $errors->getBag('item_'.$item->id);
    $contextoAtual = (string) old('_item_context') === (string) $item->id;
@endphp

<div class="border-top mt-3 pt-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h3 class="h6 fw-bold mb-1">Itens da competência</h3>
            <p class="small text-muted mb-0">Vantagens funcionais são automáticas; o setor informa somente o que varia no período.</p>
        </div>
        <span class="badge text-bg-light border">{{ $item->itens->count() }} item(ns)</span>
    </div>

    @if ($errosItem->any())
        <div class="alert alert-danger py-2" role="alert">
            <ul class="small mb-0">@foreach ($errosItem->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($itensFuncionais->isNotEmpty())
        <div class="mb-3">
            <div class="small fw-semibold text-secondary mb-2"><i class="bi bi-lock" aria-hidden="true"></i> Cadastro funcional</div>
            <div class="row g-2">
                @foreach ($itensFuncionais as $registro)
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded p-2 h-100 bg-light">
                            <div class="d-flex justify-content-between gap-2">
                                <strong class="small">{{ $registro->sigla ?: $registro->codigo_evento }} · {{ $registro->descricao }}</strong>
                                <span class="badge text-bg-secondary">Automático</span>
                            </div>
                            <div class="mt-1">{{ $registro->conteudo() }}</div>
                            @if ($registro->vigencia_inicio)
                                <div class="small text-muted mt-1">Vigência: {{ $registro->vigencia_inicio->format('d/m/Y') }} — {{ $registro->vigencia_fim?->format('d/m/Y') ?? 'atual' }}</div>
                            @endif
                            @if ($registro->referencia_documento)<div class="small text-muted">Documento: {{ $registro->referencia_documento }}</div>@endif
                            <div class="border-top mt-2 pt-2">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <span class="small fw-semibold">Comprovações</span>
                                    @if ($registro->exige_documento && $registro->evidencias->isEmpty())<span class="badge text-bg-warning">Obrigatória · pendente</span>@endif
                                </div>
                                @include('evidencias._lista', ['evidencias' => $registro->evidencias, 'podeRemover' => true])
                                @can('update', $folha)
                                    @include('evidencias._upload', [
                                        'actionEvidencia' => route('frequencia.servidores.itens.evidencias.store', [$folha, $item, $registro]),
                                        'bagEvidencia' => 'evidencia_folha_frequencia_itens_'.$registro->id,
                                        'idEvidencia' => 'item-'.$registro->id,
                                    ])
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($itensMensais->isNotEmpty())
        <div class="mb-3">
            <div class="small fw-semibold text-secondary mb-2"><i class="bi bi-calendar-check" aria-hidden="true"></i> Informados pelo setor</div>
            <div class="row g-2">
                @foreach ($itensMensais as $registro)
                    @php
                        $conteudoBruto = match ($registro->unidade_lancamento) {
                            \App\Enums\UnidadeLancamento::PERCENTUAL => $registro->percentual,
                            \App\Enums\UnidadeLancamento::VALOR => $registro->valor,
                            \App\Enums\UnidadeLancamento::DIAS, \App\Enums\UnidadeLancamento::HORAS => $registro->quantidade,
                            \App\Enums\UnidadeLancamento::NIVEL => $registro->nivel,
                            \App\Enums\UnidadeLancamento::TEXTO => $registro->texto,
                            default => null,
                        };
                    @endphp
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <div><strong>{{ $registro->sigla ?: $registro->codigo_evento }}</strong> <span class="text-muted small">{{ $registro->descricao }}</span></div>
                                <span class="badge text-bg-primary">Mensal</span>
                            </div>
                            <div class="mb-2">Registrado: <strong>{{ $registro->conteudo() }}</strong></div>
                            @can('update', $folha)
                                <form method="POST" action="{{ route('frequencia.servidores.itens.update', [$folha, $item, $registro]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="_item_context" value="{{ $item->id }}">
                                    @if ($registro->unidade_lancamento !== \App\Enums\UnidadeLancamento::MARCADOR)
                                        <label for="conteudo-{{ $registro->id }}" class="form-label small">{{ $registro->unidade_lancamento->label() }}</label>
                                        <input id="conteudo-{{ $registro->id }}" name="conteudo" value="{{ $conteudoBruto }}" class="form-control" maxlength="2000" required>
                                    @else
                                        <div class="small mb-2">Marcador ativo: <strong>Sim</strong></div>
                                    @endif
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-6">
                                            <label for="documento-{{ $registro->id }}" class="form-label small">Documento{{ $registro->evento?->exige_documento ? ' *' : '' }}</label>
                                            <input id="documento-{{ $registro->id }}" name="referencia_documento" value="{{ $registro->referencia_documento }}" class="form-control" maxlength="255" @required($registro->evento?->exige_documento)>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="obs-item-{{ $registro->id }}" class="form-label small">Observação{{ $registro->evento?->exige_observacao ? ' *' : '' }}</label>
                                            <input id="obs-item-{{ $registro->id }}" name="observacao" value="{{ $registro->observacao }}" class="form-control" maxlength="1000" @required($registro->evento?->exige_observacao)>
                                        </div>
                                    </div>
                                    <div class="text-end mt-2"><button class="btn btn-primary" type="submit">Salvar item</button></div>
                                </form>
                                <form method="POST" action="{{ route('frequencia.servidores.itens.destroy', [$folha, $item, $registro]) }}" class="text-end mt-2" onsubmit="return confirm('Remover este item mensal?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger" type="submit">Remover</button>
                                </form>
                            @else
                                <div>{{ $registro->conteudo() }}</div>
                                @if ($registro->observacao)<div class="small text-muted mt-1">{{ $registro->observacao }}</div>@endif
                                @if ($registro->referencia_documento)<div class="small text-muted">Documento: {{ $registro->referencia_documento }}</div>@endif
                            @endcan
                            <div class="border-top mt-3 pt-2">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <span class="small fw-semibold">Comprovações</span>
                                    @if ($registro->exige_documento && $registro->evidencias->isEmpty())<span class="badge text-bg-warning">Obrigatória · pendente</span>@endif
                                </div>
                                @include('evidencias._lista', ['evidencias' => $registro->evidencias, 'podeRemover' => true])
                                @can('update', $folha)
                                    @include('evidencias._upload', [
                                        'actionEvidencia' => route('frequencia.servidores.itens.evidencias.store', [$folha, $item, $registro]),
                                        'bagEvidencia' => 'evidencia_folha_frequencia_itens_'.$registro->id,
                                        'idEvidencia' => 'item-'.$registro->id,
                                    ])
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @can('update', $folha)
        @if ($eventosDisponiveis->isNotEmpty())
            <details class="border rounded p-3">
                <summary class="fw-semibold"><i class="bi bi-plus-circle" aria-hidden="true"></i> Adicionar item mensal</summary>
                <form method="POST" action="{{ route('frequencia.servidores.itens.store', [$folha, $item]) }}" class="row g-2 mt-2">
                    @csrf
                    <input type="hidden" name="_item_context" value="{{ $item->id }}">
                    <div class="col-lg-5">
                        <label for="evento-{{ $item->id }}" class="form-label small">Item homologado e autorizado <span class="text-danger">*</span></label>
                        <select id="evento-{{ $item->id }}" name="evento_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach ($eventosDisponiveis as $evento)
                                <option value="{{ $evento->id }}" @selected($contextoAtual && (string) old('evento_id') === (string) $evento->id)>{{ $evento->sigla ?: $evento->codigo_evento }} · {{ $evento->descricao }} — {{ $evento->unidade_lancamento->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="novo-conteudo-{{ $item->id }}" class="form-label small">Conteúdo</label>
                        <input id="novo-conteudo-{{ $item->id }}" name="conteudo" value="{{ $contextoAtual ? old('conteudo') : '' }}" class="form-control" maxlength="2000" aria-describedby="ajuda-conteudo-{{ $item->id }}">
                        <div id="ajuda-conteudo-{{ $item->id }}" class="form-text">Deixe vazio somente para itens do tipo marcador.</div>
                    </div>
                    <div class="col-lg-2">
                        <label for="novo-documento-{{ $item->id }}" class="form-label small">Documento</label>
                        <input id="novo-documento-{{ $item->id }}" name="referencia_documento" value="{{ $contextoAtual ? old('referencia_documento') : '' }}" class="form-control" maxlength="255">
                    </div>
                    <div class="col-lg-2">
                        <label for="nova-obs-{{ $item->id }}" class="form-label small">Observação</label>
                        <input id="nova-obs-{{ $item->id }}" name="observacao" value="{{ $contextoAtual ? old('observacao') : '' }}" class="form-control" maxlength="1000">
                    </div>
                    <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Registrar item</button></div>
                </form>
            </details>
        @elseif ($item->itens->isEmpty())
            <p class="small text-muted mb-0">Não há itens mensais homologados e autorizados disponíveis para este setor.</p>
        @endif
    @elseif ($item->itens->isEmpty())
        <p class="small text-muted mb-0">Nenhum item foi registrado nesta competência.</p>
    @endif
</div>
