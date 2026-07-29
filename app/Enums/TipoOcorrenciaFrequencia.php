<?php

namespace App\Enums;

enum TipoOcorrenciaFrequencia: string
{
    case FALTA = 'FALTA';
    case ATESTADO_MEDICO = 'ATESTADO_MEDICO';
    case FERIAS = 'FERIAS';
    case LICENCA = 'LICENCA';
    case FOLGA = 'FOLGA';
    case VIAGEM = 'VIAGEM';
    case ATIVIDADE_EXTERNA = 'ATIVIDADE_EXTERNA';
    case CURSO_CAPACITACAO = 'CURSO_CAPACITACAO';
    case COMPENSACAO = 'COMPENSACAO';
    case AFASTAMENTO_INSS = 'AFASTAMENTO_INSS';
    case EXONERACAO = 'EXONERACAO';
    case TERMINO_CONTRATO = 'TERMINO_CONTRATO';
    case PONTO_FACULTATIVO = 'PONTO_FACULTATIVO';
    case HORARIO_REDUZIDO = 'HORARIO_REDUZIDO';
    case INCONSISTENCIA_REGISTRO = 'INCONSISTENCIA_REGISTRO';
    case OUTRO = 'OUTRO';

    public function podeCoexistirCom(self $outro): bool
    {
        if ($this === $outro) {
            return false;
        }

        if ($this === self::INCONSISTENCIA_REGISTRO || $outro === self::INCONSISTENCIA_REGISTRO) {
            return true;
        }

        $modificadoresEscala = [self::PONTO_FACULTATIVO, self::HORARIO_REDUZIDO];
        $atividades = [self::VIAGEM, self::ATIVIDADE_EXTERNA, self::CURSO_CAPACITACAO];

        return (in_array($this, $modificadoresEscala, true) && in_array($outro, $atividades, true))
            || (in_array($outro, $modificadoresEscala, true) && in_array($this, $atividades, true));
    }

    public function label(): string
    {
        return match ($this) {
            self::FALTA => 'Falta',
            self::ATESTADO_MEDICO => 'Atestado médico',
            self::FERIAS => 'Férias',
            self::LICENCA => 'Licença',
            self::FOLGA => 'Folga',
            self::VIAGEM => 'Viagem',
            self::ATIVIDADE_EXTERNA => 'Atividade externa',
            self::CURSO_CAPACITACAO => 'Curso/capacitação',
            self::COMPENSACAO => 'Compensação',
            self::AFASTAMENTO_INSS => 'Afastamento pelo INSS',
            self::EXONERACAO => 'Exoneração',
            self::TERMINO_CONTRATO => 'Término de contrato',
            self::PONTO_FACULTATIVO => 'Ponto facultativo',
            self::HORARIO_REDUZIDO => 'Horário reduzido',
            self::INCONSISTENCIA_REGISTRO => 'Inconsistência de registro',
            self::OUTRO => 'Outro',
        };
    }
}
