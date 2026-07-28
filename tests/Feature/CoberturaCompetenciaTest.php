<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\LotacaoHistorico;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\CoberturaFrequenciaService;
use App\Services\CompetenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CoberturaCompetenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_identifies_a_sector_that_has_not_started_its_frequency(): void
    {
        [$competencia, $central, $setorA, $setorB] = $this->cenarioComDoisSetores();
        $this->criarFolhaAprovada($competencia, $setorA, $central);

        $resumo = app(CoberturaFrequenciaService::class)->resumo($competencia);

        $this->assertSame(2, $resumo['setores_esperados']);
        $this->assertSame(1, $resumo['aprovadas']);
        $this->assertSame(1, $resumo['nao_iniciadas']);
        $this->assertFalse($resumo['pronta_para_fechar']);

        $this->actingAs($central)
            ->get(route('admin.competencias.cobertura', $competencia))
            ->assertOk()
            ->assertSee($setorB->nome)
            ->assertSee('Não iniciada');
    }

    public function test_competence_cannot_close_until_every_required_frequency_is_approved(): void
    {
        [$competencia, $central, $setorA] = $this->cenarioComDoisSetores();
        $this->criarFolhaAprovada($competencia, $setorA, $central);
        $this->actingAs($central);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Todas as frequências mensais obrigatórias devem estar aprovadas.');

        app(CompetenciaService::class)->fechar($competencia);
    }

    public function test_competence_closes_after_all_required_frequencies_are_approved(): void
    {
        [$competencia, $central, $setorA, $setorB] = $this->cenarioComDoisSetores();
        $this->criarFolhaAprovada($competencia, $setorA, $central);
        $this->criarFolhaAprovada($competencia, $setorB, $central);
        $this->actingAs($central);

        app(CompetenciaService::class)->fechar($competencia);

        $this->assertSame(CompetenciaStatus::FECHADA, $competencia->fresh()->status);
        $this->assertNotNull($competencia->fresh()->fechada_em);
    }

    public function test_late_admission_missing_from_approved_sheet_blocks_closure(): void
    {
        [$competencia, $central, $setorA, $setorB] = $this->cenarioComDoisSetores();
        $this->criarFolhaAprovada($competencia, $setorA, $central);
        $this->criarFolhaAprovada($competencia, $setorB, $central);

        Servidor::create([
            'matricula' => 'COB-003',
            'nome' => 'Servidor admitido depois',
            'setor_id' => $setorA->id,
            'data_admissao' => '2026-07-01',
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);

        $cobertura = app(CoberturaFrequenciaService::class)->porCompetencia($competencia);
        $itemSetorA = $cobertura->first(fn (array $item): bool => $item['setor']->is($setorA));
        $resumo = app(CoberturaFrequenciaService::class)->resumo($competencia, $cobertura);

        $this->assertSame(2, $itemSetorA['servidores_esperados']);
        $this->assertSame(1, $itemSetorA['servidores_na_folha']);
        $this->assertCount(1, $itemSetorA['servidores_faltantes']);
        $this->assertTrue($itemSetorA['bloqueia_fechamento']);
        $this->assertSame(1, $resumo['divergencias_populacao']);
        $this->assertFalse($resumo['pronta_para_fechar']);

        $this->actingAs($central);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('divergência populacional');

        app(CompetenciaService::class)->fechar($competencia);
    }

    public function test_transfer_swap_is_detected_even_when_sector_counts_are_equal(): void
    {
        [$competencia, $central, $setorA, $setorB] = $this->cenarioComDoisSetores();
        $this->criarFolhaAprovada($competencia, $setorA, $central);
        $this->criarFolhaAprovada($competencia, $setorB, $central);
        $servidorA = Servidor::firstWhere('setor_id', $setorA->id);
        $servidorB = Servidor::firstWhere('setor_id', $setorB->id);

        $this->registrarTransferenciaNoPeriodo($servidorA, $setorA, $setorB);
        $this->registrarTransferenciaNoPeriodo($servidorB, $setorB, $setorA);

        $cobertura = app(CoberturaFrequenciaService::class)->porCompetencia($competencia);

        foreach ($cobertura as $item) {
            $this->assertSame(1, $item['servidores_esperados']);
            $this->assertSame(1, $item['servidores_na_folha']);
            $this->assertCount(1, $item['servidores_faltantes']);
            $this->assertCount(1, $item['servidores_excedentes']);
            $this->assertTrue($item['bloqueia_fechamento']);
        }

        $this->assertFalse(app(CoberturaFrequenciaService::class)->resumo($competencia, $cobertura)['pronta_para_fechar']);
    }

    /** @return array{Competencia, User, Setor, Setor} */
    private function cenarioComDoisSetores(): array
    {
        $setorA = Setor::create(['nome' => 'Unidade Norte', 'sigla' => 'NORTE', 'ativo' => true]);
        $setorB = Setor::create(['nome' => 'Unidade Sul', 'sigla' => 'SUL', 'ativo' => true]);
        $central = User::create([
            'name' => 'Central',
            'email' => 'central-cobertura@example.test',
            'password' => 'senha-segura',
            'setor_id' => $setorA->id,
            'role' => UserRole::CENTRAL,
        ]);
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $central->id,
        ]);

        foreach ([$setorA, $setorB] as $indice => $setor) {
            Servidor::create([
                'matricula' => 'COB-00'.($indice + 1),
                'nome' => 'Servidor '.($indice + 1),
                'setor_id' => $setor->id,
                'origem_registro' => 'MANUAL',
                'ativo' => true,
            ]);
        }

        return [$competencia, $central, $setorA, $setorB];
    }

    private function criarFolhaAprovada(Competencia $competencia, Setor $setor, User $central): FolhaFrequencia
    {
        $folha = FolhaFrequencia::create([
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'status' => FolhaFrequenciaStatus::APROVADA,
            'criado_por_id' => $central->id,
            'finalizado_por_id' => $central->id,
            'finalizada_em' => now(),
            'conferido_por_id' => $central->id,
            'conferida_em' => now(),
        ]);

        Servidor::all()
            ->filter(fn (Servidor $servidor): bool => $servidor->estaAtivoNaCompetencia($competencia->referencia)
                && $servidor->setorNaCompetencia($competencia->referencia) === $setor->id)
            ->each(fn (Servidor $servidor) => $folha->servidores()->create([
                'servidor_id' => $servidor->id,
                'matricula' => $servidor->matricula,
                'nome' => $servidor->nome,
                'status' => 'INTEGRAL',
            ]));

        return $folha;
    }

    private function registrarTransferenciaNoPeriodo(Servidor $servidor, Setor $origem, Setor $destino): void
    {
        LotacaoHistorico::create([
            'servidor_id' => $servidor->id,
            'setor_id' => $origem->id,
            'data_inicio' => '2026-06-11',
            'data_fim' => '2026-06-30',
        ]);
        LotacaoHistorico::create([
            'servidor_id' => $servidor->id,
            'setor_id' => $destino->id,
            'data_inicio' => '2026-07-01',
        ]);
        Servidor::withoutEvents(fn () => $servidor->update(['setor_id' => $destino->id]));
    }
}
