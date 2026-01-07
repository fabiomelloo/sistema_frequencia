<?php

namespace App\Services;

use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Enums\TipoEvento;
use Carbon\Carbon;

class ValidacaoLancamentoService
{
    public function validarRegrasNegocio(array $data): array
    {
        $errors = [];

        $data = $this->normalizarDados($data);

        $servidor = Servidor::find($data['servidor_id'] ?? null);
        $evento = EventoFolha::find($data['evento_id'] ?? null);

        if (!$servidor || !$evento) {
            return ['servidor_id' => ['Dados inválidos.']];
        }

        if (isset($data['adicional_turno']) && $data['adicional_turno'] > 0) {
            if (!$this->podeAplicarAdicionalTurno($servidor, $evento)) {
                $errors['adicional_turno'] = [
                    'Adicional de turno só pode ser aplicado para servidores com função de vigia.'
                ];
            }
        }

        if (isset($data['adicional_noturno']) && $data['adicional_noturno'] > 0) {
            if (!$this->podeAplicarAdicionalNoturno($servidor, $evento)) {
                $errors['adicional_noturno'] = [
                    'Adicional noturno só pode ser aplicado quando há trabalho noturno real.'
                ];
            }
        }

        if (!$this->validarInsalubridadePericulosidade(
            $data['porcentagem_insalubridade'] ?? null,
            $data['porcentagem_periculosidade'] ?? null
        )) {
            $errors['porcentagem_insalubridade'] = [
                'Insalubridade e periculosidade não podem coexistir.'
            ];
            $errors['porcentagem_periculosidade'] = [
                'Periculosidade e insalubridade não podem coexistir.'
            ];
        }

        if (!$this->validarCoerenciaDiasAdicionais(
            $data['dias_lancados'] ?? null,
            $data['dias_noturnos'] ?? null,
            $data['valor'] ?? null,
            $data['porcentagem_insalubridade'] ?? null,
            $data['porcentagem_periculosidade'] ?? null,
            $data['adicional_turno'] ?? null,
            $data['adicional_noturno'] ?? null,
            $evento
        )) {
            $errors['dias_lancados'] = [
                'Dias trabalhados devem estar coerentes com os adicionais aplicados.'
            ];
        }

        if (isset($data['dias_noturnos']) && isset($data['dias_lancados'])) {
            if ($data['dias_noturnos'] > $data['dias_lancados']) {
                $errors['dias_noturnos'] = [
                    'Dias noturnos não podem ser maiores que dias lançados.'
                ];
            }
        }

        if (isset($data['dias_lancados']) && $data['dias_lancados'] > 0) {
            $diasNoMes = Carbon::now()->daysInMonth;
            if ($data['dias_lancados'] > $diasNoMes) {
                $errors['dias_lancados'] = [
                    "Dias lançados não podem ser maiores que os dias do mês ({$diasNoMes})."
                ];
            }
        }

        return $errors;
    }

    public function podeAplicarAdicionalTurno(Servidor $servidor, EventoFolha $evento): bool
    {
        if ($evento->tipo_evento === TipoEvento::ADICIONAL_TURNO) {
            return $servidor->funcao_vigia === true;
        }

        return true;
    }

    public function podeAplicarAdicionalNoturno(Servidor $servidor, EventoFolha $evento): bool
    {
        if ($evento->tipo_evento === TipoEvento::ADICIONAL_NOTURNO) {
            return $servidor->trabalha_noturno === true;
        }

        return true;
    }

    public function validarInsalubridadePericulosidade(
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade
    ): bool {
        if ($porcentagemInsalubridade && $porcentagemPericulosidade) {
            return false;
        }
        return true;
    }

    private function normalizarDados(array $data): array
    {
        $camposNumericos = [
            'dias_lancados',
            'dias_noturnos',
            'valor',
            'porcentagem_insalubridade',
            'porcentagem_periculosidade',
            'adicional_turno',
            'adicional_noturno',
        ];

        foreach ($camposNumericos as $campo) {
            if (isset($data[$campo])) {
                $valor = $data[$campo];
                
                if ($valor === '' || $valor === null) {
                    $data[$campo] = null;
                    continue;
                }

                if (is_string($valor) && is_numeric($valor)) {
                    if (in_array($campo, ['dias_lancados', 'dias_noturnos', 'porcentagem_insalubridade', 'porcentagem_periculosidade'])) {
                        $data[$campo] = (int) $valor;
                    } else {
                        $data[$campo] = (float) $valor;
                    }
                } elseif (is_numeric($valor)) {
                    if (in_array($campo, ['dias_lancados', 'dias_noturnos', 'porcentagem_insalubridade', 'porcentagem_periculosidade'])) {
                        $data[$campo] = (int) $valor;
                    } else {
                        $data[$campo] = (float) $valor;
                    }
                }
            }
        }

        return $data;
    }

    public function validarCoerenciaDiasAdicionais(
        ?int $diasLancados,
        ?int $diasNoturnos,
        ?float $valor,
        ?int $porcentagemInsalubridade,
        ?int $porcentagemPericulosidade,
        ?float $adicionalTurno,
        ?float $adicionalNoturno,
        ?EventoFolha $evento = null
    ): bool {
        if ($diasLancados && $diasLancados > 0) {
            if ($evento && $evento->exige_porcentagem) {
                if (($porcentagemInsalubridade !== null && $porcentagemInsalubridade > 0) ||
                    ($porcentagemPericulosidade !== null && $porcentagemPericulosidade > 0)) {
                    return true;
                }
                return true;
            }

            if ($evento && $evento->exige_valor) {
                if ($valor !== null && $valor > 0) {
                    return true;
                }
                return true;
            }

            $temValorOuAdicional = 
                ($valor !== null && $valor > 0)
                || ($porcentagemInsalubridade !== null && $porcentagemInsalubridade > 0)
                || ($porcentagemPericulosidade !== null && $porcentagemPericulosidade > 0)
                || ($adicionalTurno !== null && $adicionalTurno > 0)
                || ($adicionalNoturno !== null && $adicionalNoturno > 0);

            if (!$temValorOuAdicional) {
                return false;
            }
        }

        if ($adicionalNoturno !== null && $adicionalNoturno > 0) {
            if ($diasNoturnos === null || $diasNoturnos <= 0) {
                return false;
            }
        }

        return true;
    }
}
