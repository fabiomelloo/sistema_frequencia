<?php

namespace App\Enums;

enum TipoDesignacaoFuncional: string
{
    case DAS = 'DAS';
    case FGT = 'FGT';
    case FCT = 'FCT';
    case GRT = 'GRT';
    case OUTRA = 'OUTRA';

    public function label(): string
    {
        return match ($this) {
            self::DAS => 'DAS — Cargo em comissão',
            self::FGT => 'FGT — Função gratificada temporária',
            self::FCT => 'FCT — Função gratificada de confiança',
            self::GRT => 'GRT — Responsabilidade técnica',
            self::OUTRA => 'Outra designação',
        };
    }
}
