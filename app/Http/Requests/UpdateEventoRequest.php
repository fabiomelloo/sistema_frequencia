<?php

namespace App\Http\Requests;

class UpdateEventoRequest extends EventoRequest
{
    public function authorize(): bool
    {
        $evento = $this->route('evento');

        return $evento && ($this->user()?->can('update', $evento) ?? false);
    }

    public function rules(): array
    {
        $eventoId = $this->route('evento')->id;

        return array_merge([
            'codigo_evento' => ['required', 'string', 'max:10', 'unique:eventos_folha,codigo_evento,'.$eventoId],
        ], $this->regrasComuns());
    }
}
