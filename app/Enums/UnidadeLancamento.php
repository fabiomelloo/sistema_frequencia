<?php

namespace App\Enums;

enum UnidadeLancamento: string
{
    case MARCADOR = 'MARCADOR';
    case DIAS = 'DIAS';
    case HORAS = 'HORAS';
    case PERCENTUAL = 'PERCENTUAL';
    case VALOR = 'VALOR';
    case NIVEL = 'NIVEL';
    case TEXTO = 'TEXTO';

    public function label(): string
    {
        return match ($this) {
            self::MARCADOR => 'Marcador (sim/não)',
            self::DIAS => 'Quantidade de dias',
            self::HORAS => 'Quantidade de horas',
            self::PERCENTUAL => 'Percentual',
            self::VALOR => 'Valor monetário',
            self::NIVEL => 'Nível ou referência',
            self::TEXTO => 'Informação textual',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::MARCADOR => 'Registra apenas a ocorrência do item.',
            self::DIAS => 'O setor informa uma quantidade de dias.',
            self::HORAS => 'O setor informa uma quantidade de horas.',
            self::PERCENTUAL => 'O item utiliza um percentual permitido pela regra.',
            self::VALOR => 'O item recebe ou calcula um valor em reais.',
            self::NIVEL => 'O item utiliza uma faixa, classe ou nível funcional.',
            self::TEXTO => 'O item exige uma referência textual estruturada.',
        };
    }
}
