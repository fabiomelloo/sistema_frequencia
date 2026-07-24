<?php

namespace App\Enums;

enum ImportacaoStatus: string
{
    case PENDENTE = 'PENDENTE';
    case PROCESSADA = 'PROCESSADA';
    case INVALIDA = 'INVALIDA';
}
