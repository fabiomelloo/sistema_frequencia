<?php

namespace App\Enums;

enum FolhaFrequenciaStatus: string
{
    case RASCUNHO = 'RASCUNHO';
    case FINALIZADA = 'FINALIZADA';
    case APROVADA = 'APROVADA';
    case DEVOLVIDA = 'DEVOLVIDA';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Em preenchimento',
            self::FINALIZADA => 'Aguardando conferência',
            self::APROVADA => 'Aprovada',
            self::DEVOLVIDA => 'Devolvida para correção',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::RASCUNHO => 'warning',
            self::FINALIZADA => 'info',
            self::APROVADA => 'success',
            self::DEVOLVIDA => 'danger',
        };
    }

    public function editavelPeloSetor(): bool
    {
        return in_array($this, [self::RASCUNHO, self::DEVOLVIDA], true);
    }

    public function aguardaConferencia(): bool
    {
        return $this === self::FINALIZADA;
    }
}
