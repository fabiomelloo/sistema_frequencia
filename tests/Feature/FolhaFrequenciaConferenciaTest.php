<?php

namespace Tests\Feature;

use App\Enums\CompetenciaStatus;
use App\Enums\ConferenciaServidorStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolhaFrequenciaConferenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_can_approve_a_submitted_monthly_frequency(): void
    {
        [$folha, $setorial, $central] = $this->cenarioComFolhaFinalizada();
        $item = $folha->servidores()->firstOrFail();

        $segundoServidor = Servidor::create([
            'matricula' => 'MAT-CONF-002',
            'nome' => 'Segundo servidor para Conferência',
            'setor_id' => $folha->setor_id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $segundoItem = $folha->servidores()->create([
            'servidor_id' => $segundoServidor->id,
            'matricula' => $segundoServidor->matricula,
            'nome' => $segundoServidor->nome,
            'status' => FrequenciaServidorStatus::INTEGRAL,
            'atualizado_por_id' => $setorial->id,
            'preenchida_em' => now(),
        ]);

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertSessionHasErrors('conferencia');

        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $item]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertSessionHasErrors('conferencia');

        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $segundoItem]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertRedirect(route('painel-frequencias.index'))
            ->assertSessionHasNoErrors();

        $folha->refresh();
        $this->assertSame(FolhaFrequenciaStatus::APROVADA, $folha->status);
        $this->assertSame($central->id, $folha->conferido_por_id);
        $this->assertNotNull($folha->conferida_em);
        $this->assertDatabaseHas('notificacoes', [
            'user_id' => $setorial->id,
            'tipo' => 'FREQUENCIA_APROVADA',
        ]);
    }

    public function test_central_return_requires_reason_and_reopens_setorial_editing(): void
    {
        [$folha, $setorial, $central] = $this->cenarioComFolhaFinalizada();

        $this->actingAs($central)
            ->post(route('painel-frequencias.devolver', $folha), ['motivo_devolucao' => 'Curto'])
            ->assertSessionHasErrors('motivo_devolucao');

        $motivo = 'Confira a observação e os dias informados para o servidor.';
        $this->actingAs($central)
            ->post(route('painel-frequencias.devolver', $folha), ['motivo_devolucao' => $motivo])
            ->assertSessionHasErrors('conferencia');

        $item = $folha->servidores()->firstOrFail();
        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $item]), [
                'status' => ConferenciaServidorStatus::DIVERGENTE->value,
                'apontamento' => 'Curto',
            ])
            ->assertSessionHasErrors('apontamento', null, "conferencia_{$item->id}");

        $apontamento = 'Os dias informados não correspondem às ocorrências registradas.';
        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $item]), [
                'status' => ConferenciaServidorStatus::DIVERGENTE->value,
                'apontamento' => $apontamento,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($central)
            ->post(route('painel-frequencias.devolver', $folha), ['motivo_devolucao' => $motivo])
            ->assertRedirect(route('painel-frequencias.index'))
            ->assertSessionHasNoErrors();

        $folha->refresh();
        $this->assertSame(FolhaFrequenciaStatus::DEVOLVIDA, $folha->status);
        $this->assertSame($motivo, $folha->motivo_devolucao);

        $this->actingAs($setorial)->get(route('frequencia.show', $folha))
            ->assertOk()
            ->assertSee($apontamento);

        $this->actingAs($setorial)
            ->put(route('frequencia.servidores.update', [$folha, $item]), [
                'status' => FrequenciaServidorStatus::INTEGRAL->value,
                'observacao_geral' => 'Informação revisada pelo setor.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Informação revisada pelo setor.', $item->fresh()->observacao_geral);

        $this->actingAs($setorial)->post(route('frequencia.finalizar', $folha))->assertSessionHasNoErrors();
        $this->assertSame(2, $folha->fresh()->rodada_conferencia);
        $this->assertDatabaseHas('conferencias_frequencia_servidor', [
            'folha_frequencia_servidor_id' => $item->id,
            'rodada' => 1,
            'status' => ConferenciaServidorStatus::DIVERGENTE->value,
            'apontamento' => $apontamento,
        ]);

        $this->actingAs($central)
            ->post(route('painel-frequencias.aprovar', $folha))
            ->assertSessionHasErrors('conferencia');
        $this->actingAs($central)
            ->post(route('painel-frequencias.servidores.conferir', [$folha, $item]), [
                'status' => ConferenciaServidorStatus::CONFERIDO->value,
                'apontamento' => 'Correção conferida na segunda rodada.',
            ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conferencias_frequencia_servidor', [
            'folha_frequencia_servidor_id' => $item->id,
            'rodada' => 2,
            'status' => ConferenciaServidorStatus::CONFERIDO->value,
            'apontamento' => null,
        ]);
    }

    public function test_setorial_user_cannot_access_the_central_frequency_panel(): void
    {
        [$folha, $setorial] = $this->cenarioComFolhaFinalizada();

        $this->actingAs($setorial)
            ->get(route('painel-frequencias.show', $folha))
            ->assertForbidden();
    }

    /** @return array{FolhaFrequencia, User, User} */
    private function cenarioComFolhaFinalizada(): array
    {
        $setor = Setor::create(['nome' => 'Setor de Teste', 'sigla' => 'TST', 'ativo' => true]);
        $setorial = $this->criarUsuario($setor, UserRole::SETORIAL, 'setorial-fluxo@example.test');
        $central = $this->criarUsuario($setor, UserRole::CENTRAL, 'central-fluxo@example.test');
        $competencia = Competencia::create([
            'referencia' => '2026-07',
            'status' => CompetenciaStatus::ABERTA,
            'aberta_por' => $central->id,
        ]);
        $servidor = Servidor::create([
            'matricula' => 'MAT-CONF-001',
            'nome' => 'Servidor para Conferência',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);
        $folha = FolhaFrequencia::create([
            'setor_id' => $setor->id,
            'competencia_id' => $competencia->id,
            'status' => FolhaFrequenciaStatus::FINALIZADA,
            'rodada_conferencia' => 1,
            'criado_por_id' => $setorial->id,
            'finalizado_por_id' => $setorial->id,
            'finalizada_em' => now(),
        ]);
        $folha->servidores()->create([
            'servidor_id' => $servidor->id,
            'matricula' => $servidor->matricula,
            'nome' => $servidor->nome,
            'status' => FrequenciaServidorStatus::INTEGRAL,
            'atualizado_por_id' => $setorial->id,
            'preenchida_em' => now(),
        ]);

        return [$folha, $setorial, $central];
    }

    private function criarUsuario(Setor $setor, UserRole $role, string $email): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $email,
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => $role,
        ]);
    }
}
