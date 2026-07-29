<?php

namespace App\Enums;

enum ImportacaoLinhaStatus: string
{
    case VALIDA = 'VALIDA';
    case INVALIDA = 'INVALIDA';
    case PROCESSADA = 'PROCESSADA';
}
