<?php

namespace App\Enums;

enum OrigemInformacaoItem: string
{
    case CADASTRO_FUNCIONAL = 'CADASTRO_FUNCIONAL';
    case SETOR_MENSAL = 'SETOR_MENSAL';
    case OCORRENCIA = 'OCORRENCIA';
    case SISTEMA = 'SISTEMA';

    public function label(): string
    {
        return match ($this) {
            self::CADASTRO_FUNCIONAL => 'Cadastro funcional vigente',
            self::SETOR_MENSAL => 'Informação mensal do setor',
            self::OCORRENCIA => 'Derivada de ocorrência',
            self::SISTEMA => 'Calculada pelo sistema',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::CADASTRO_FUNCIONAL => 'Vem do histórico funcional e não deve ser redigitada todo mês.',
            self::SETOR_MENSAL => 'É informada ou confirmada pelo setor em cada competência.',
            self::OCORRENCIA => 'É formada a partir dos dias e fatos registrados nas ocorrências.',
            self::SISTEMA => 'É obtida automaticamente por uma regra validada.',
        };
    }
}
