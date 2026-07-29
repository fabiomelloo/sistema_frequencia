<?php

namespace App\Http\Requests;

use App\Models\EventoFolha;

class StoreEventoRequest extends EventoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EventoFolha::class) ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'codigo_evento' => ['required', 'string', 'max:10', 'unique:eventos_folha,codigo_evento'],
        ], $this->regrasComuns());
    }
}
