@php
    $errosUpload = $errors->getBag($bagEvidencia);
@endphp

<form method="POST" action="{{ $actionEvidencia }}" enctype="multipart/form-data" class="border rounded p-3 mt-3">
    @csrf
    <fieldset>
        <legend class="h6 fw-semibold">Anexar documento comprobatório</legend>
        <div class="row g-2 align-items-end">
            <div class="col-12">
                <label for="arquivo-{{ $idEvidencia }}" class="form-label">Arquivo <span class="text-danger">*</span></label>
                <input id="arquivo-{{ $idEvidencia }}" name="arquivo" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="form-control @if($errosUpload->has('arquivo')) is-invalid @endif" aria-describedby="ajuda-arquivo-{{ $idEvidencia }}" required>
                @if ($errosUpload->has('arquivo'))<div class="invalid-feedback" role="alert">{{ $errosUpload->first('arquivo') }}</div>@endif
                <div id="ajuda-arquivo-{{ $idEvidencia }}" class="form-text">PDF, JPG ou PNG, com no máximo 10 MB. O arquivo ficará em armazenamento privado.</div>
            </div>
            <div class="col-12">
                <label for="descricao-evidencia-{{ $idEvidencia }}" class="form-label">Descrição</label>
                <input id="descricao-evidencia-{{ $idEvidencia }}" name="descricao" value="{{ $errosUpload->any() ? old('descricao') : '' }}" maxlength="500" class="form-control @if($errosUpload->has('descricao')) is-invalid @endif" placeholder="Ex.: atestado referente aos dias 12 e 13">
                @if ($errosUpload->has('descricao'))<div class="invalid-feedback" role="alert">{{ $errosUpload->first('descricao') }}</div>@endif
            </div>
            <div class="col-12 d-grid d-md-flex justify-content-md-end"><button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>Anexar</button></div>
        </div>
    </fieldset>
</form>
