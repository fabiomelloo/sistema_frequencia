<?php

namespace App\Http\Requests;

use App\Http\Requests\StoreLancamentoSetorialRequest;

class UpdateLancamentoSetorialRequest extends StoreLancamentoSetorialRequest
{
    // Herda todas as regras do StoreLancamentoSetorialRequest
    // Quando as regras de update divergirem de store, sobrescrever rules() aqui
}
