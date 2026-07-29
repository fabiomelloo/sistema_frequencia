<?php

namespace App\Enums;

enum EvidenciaStatus: string
{
    case PENDENTE = 'PENDENTE';
    case ACEITA = 'ACEITA';
    case RECUSADA = 'RECUSADA';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Aguardando conferência',
            self::ACEITA => 'Documento aceito',
            self::RECUSADA => 'Documento recusado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::PENDENTE => 'warning',
            self::ACEITA => 'success',
            self::RECUSADA => 'danger',
        };
    }
}
