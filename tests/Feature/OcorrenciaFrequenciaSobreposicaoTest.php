<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\TipoOcorrenciaFrequencia;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcorrenciaFrequenciaSobreposicaoTest extends TestCase
{
    use RefreshDatabase;

    private Setor $setor;

    private User $usuario;

    private Servidor $servidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setor = Setor::create(['nome' => 'Setor de Teste', 'sigla' => 'TST', 'ativo' => true]);
        $this->usuario = User::create([
            'name' => 'Setorial',
            'email' => 'sobreposicao@example.test',
            'password' => 'senha-segura',
            'setor_id' => $this->setor->id,
            'role' => UserRole::SETORIAL,
        ]);
        $this->servidor = Servidor::create([
            'matricula' => 'OVR001',
            'nome' => 'Servidor de teste',
            'setor_id' => $this->setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
    }

    public function test_rejeita_sobreposicoes_totais_parciais_e_entre_formas_de_data(): void
    {
        $competencia = $this->competencia('2026-07', '2026-06-11', '2026-07-10');
        $this->registrar($competencia, TipoOcorrenciaFrequencia::FALTA, '2026-06-15', '2026-06-20');

        $conflitos = [
            [TipoOcorrenciaFrequencia::FALTA, '2026-06-15', '2026-06-20'],
            [TipoOcorrenciaFrequencia::FALTA, '2026-06-18', '2026-06-22'],
            [TipoOcorrenciaFrequencia::ATESTADO_MEDICO, '2026-06-16', '2026-06-17'],
        ];

        foreach ($conflitos as [$tipo, $inicio, $fim]) {
            $this->actingAs($this->usuario)
                ->post(route('ocorrencias.store'), $this->payload($competencia, $tipo, $inicio, $fim))
                ->assertSessionHasErrors('data_inicio');
        }

        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload(
                $competencia,
                TipoOcorrenciaFrequencia::FALTA,
                dias: ['2026-06-19'],
            ))
            ->assertSessionHasErrors('dias_especificos');

        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload(
                $competencia,
                TipoOcorrenciaFrequencia::FALTA,
                '2026-06-21',
                '2026-06-22',
            ))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ocorrencias_frequencia', 2);
    }

    public function test_aplica_matriz_de_coexistencia_e_rejeita_mesmo_tipo(): void
    {
        $competencia = $this->competencia('2026-07', '2026-06-11', '2026-07-10');
        $this->registrar($competencia, TipoOcorrenciaFrequencia::VIAGEM, '2026-06-15', '2026-06-20');

        foreach ([TipoOcorrenciaFrequencia::HORARIO_REDUZIDO, TipoOcorrenciaFrequencia::INCONSISTENCIA_REGISTRO] as $tipo) {
            $this->actingAs($this->usuario)
                ->post(route('ocorrencias.store'), $this->payload($competencia, $tipo, '2026-06-16', '2026-06-17'))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload(
                $competencia,
                TipoOcorrenciaFrequencia::HORARIO_REDUZIDO,
                '2026-06-17',
                '2026-06-18',
            ))
            ->assertSessionHasErrors('data_inicio');

        $this->assertDatabaseCount('ocorrencias_frequencia', 3);
    }

    public function test_detecta_conflito_pela_data_fisica_entre_competencias(): void
    {
        $competenciaJulho = $this->competencia('2026-07', '2026-06-11', '2026-07-10');
        $competenciaAgosto = $this->competencia('2026-08', '2026-07-01', '2026-08-10');
        $this->registrar($competenciaJulho, TipoOcorrenciaFrequencia::FERIAS, '2026-07-01', '2026-07-05');

        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload(
                $competenciaAgosto,
                TipoOcorrenciaFrequencia::LICENCA,
                '2026-07-04',
                '2026-07-08',
            ))
            ->assertSessionHasErrors('data_inicio');

        $this->assertDatabaseCount('ocorrencias_frequencia', 1);
    }

    public function test_atualizacao_conflitante_faz_rollback_integral(): void
    {
        $competencia = $this->competencia('2026-07', '2026-06-11', '2026-07-10');
        $this->registrar($competencia, TipoOcorrenciaFrequencia::FERIAS, '2026-06-15', '2026-06-20');
        $outra = $this->registrar($competencia, TipoOcorrenciaFrequencia::LICENCA, '2026-06-25', '2026-06-28');

        $this->actingAs($this->usuario)
            ->put(route('ocorrencias.update', $outra), $this->payload(
                $competencia,
                TipoOcorrenciaFrequencia::LICENCA,
                '2026-06-18',
                '2026-06-26',
            ))
            ->assertSessionHasErrors('data_inicio');

        $outra->refresh();
        $this->assertSame('2026-06-25', $outra->data_inicio->toDateString());
        $this->assertSame('2026-06-28', $outra->data_fim->toDateString());
    }

    public function test_rejeita_periodo_e_dias_especificos_na_mesma_ocorrencia(): void
    {
        $competencia = $this->competencia('2026-07', '2026-06-11', '2026-07-10');

        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload(
                $competencia,
                TipoOcorrenciaFrequencia::FALTA,
                '2026-06-15',
                '2026-06-20',
                ['2026-06-18'],
            ))
            ->assertSessionHasErrors('data_inicio');

        $this->assertDatabaseCount('ocorrencias_frequencia', 0);
    }

    private function competencia(string $referencia, string $inicio, string $fim): Competencia
    {
        return Competencia::create([
            'referencia' => $referencia,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $this->usuario->id,
        ]);
    }

    private function registrar(
        Competencia $competencia,
        TipoOcorrenciaFrequencia $tipo,
        ?string $inicio = null,
        ?string $fim = null,
        array $dias = [],
    ): OcorrenciaFrequencia {
        $this->actingAs($this->usuario)
            ->post(route('ocorrencias.store'), $this->payload($competencia, $tipo, $inicio, $fim, $dias))
            ->assertSessionHasNoErrors();

        return OcorrenciaFrequencia::query()->latest('id')->firstOrFail();
    }

    private function payload(
        Competencia $competencia,
        TipoOcorrenciaFrequencia $tipo,
        ?string $inicio = null,
        ?string $fim = null,
        array $dias = [],
    ): array {
        return [
            'servidor_id' => $this->servidor->id,
            'competencia_id' => $competencia->id,
            'tipo' => $tipo->value,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'dias_especificos' => $dias,
            'possui_comprovacao' => '0',
        ];
    }
}
