<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
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
        return FolhaFrequencia::create([
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'status' => FolhaFrequenciaStatus::APROVADA,
            'criado_por_id' => $central->id,
            'finalizado_por_id' => $central->id,
            'finalizada_em' => now(),
            'conferido_por_id' => $central->id,
            'conferida_em' => now(),
        ]);
    }
}
