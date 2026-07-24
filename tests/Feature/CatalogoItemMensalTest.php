<?php

namespace Tests\Feature;

use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use App\Enums\UserRole;
use App\Models\EventoFolha;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoItemMensalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_documented_and_validated_monthly_item_rule(): void
    {
        $admin = $this->criarUsuario(UserRole::ADMIN, 'admin-catalogo@example.test');

        $this->actingAs($admin)
            ->post(route('admin.eventos.store'), $this->dadosEvento([
                'regra_validada' => '1',
                'instrucoes_lancamento' => 'Aplicar somente aos servidores com direito funcional vigente.',
            ]))
            ->assertRedirect(route('admin.eventos.index'))
            ->assertSessionHasNoErrors();

        $evento = EventoFolha::firstOrFail();
        $this->assertSame('INS', $evento->sigla);
        $this->assertSame(UnidadeLancamento::PERCENTUAL, $evento->unidade_lancamento);
        $this->assertSame(OrigemInformacaoItem::SETOR_MENSAL, $evento->origem_informacao);
        $this->assertTrue($evento->gera_efeito_financeiro);
        $this->assertTrue($evento->regra_validada);
        $this->assertSame($admin->id, $evento->regra_validada_por_id);
        $this->assertNotNull($evento->regra_validada_em);

        $this->actingAs($admin)->get(route('admin.eventos.create'))
            ->assertOk()->assertSee('Forma de lançamento');
        $this->actingAs($admin)->get(route('admin.eventos.edit', $evento))
            ->assertOk()->assertSee('Regra conferida e validada');
        $this->actingAs($admin)->get(route('admin.eventos.show', $evento))
            ->assertOk()->assertSee('Aplicar somente aos servidores');
    }

    public function test_rule_cannot_be_validated_without_official_instructions(): void
    {
        $admin = $this->criarUsuario(UserRole::ADMIN, 'admin-sem-regra@example.test');

        $this->actingAs($admin)
            ->post(route('admin.eventos.store'), $this->dadosEvento([
                'regra_validada' => '1',
                'instrucoes_lancamento' => '',
            ]))
            ->assertSessionHasErrors('instrucoes_lancamento');

        $this->assertDatabaseCount('eventos_folha', 0);
    }

    public function test_central_can_view_but_cannot_change_the_monthly_item_catalog(): void
    {
        $central = $this->criarUsuario(UserRole::CENTRAL, 'central-catalogo@example.test');
        $evento = EventoFolha::create([
            'codigo_evento' => 'INS001',
            'sigla' => 'INS',
            'descricao' => 'Insalubridade',
            'tipo_evento' => TipoEvento::INSALUBRIDADE,
            'unidade_lancamento' => UnidadeLancamento::PERCENTUAL,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL,
            'gera_efeito_financeiro' => true,
            'exige_porcentagem' => true,
            'regra_validada' => false,
            'ativo' => true,
        ]);

        $this->actingAs($central)
            ->get(route('admin.eventos.index'))
            ->assertOk()
            ->assertSee('Revisão pendente');

        $this->actingAs($central)
            ->put(route('admin.eventos.update', $evento), $this->dadosEvento())
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function dadosEvento(array $sobrescrever = []): array
    {
        return array_merge([
            'codigo_evento' => 'INS001',
            'sigla' => 'ins',
            'descricao' => 'Insalubridade',
            'tipo_evento' => TipoEvento::INSALUBRIDADE->value,
            'unidade_lancamento' => UnidadeLancamento::PERCENTUAL->value,
            'origem_informacao' => OrigemInformacaoItem::SETOR_MENSAL->value,
            'gera_efeito_financeiro' => '1',
            'exige_porcentagem' => '1',
            'exige_observacao' => '1',
            'ativo' => '1',
        ], $sobrescrever);
    }

    private function criarUsuario(UserRole $role, string $email): User
    {
        $setor = Setor::create(['nome' => 'Setor do Catálogo', 'sigla' => 'CAT', 'ativo' => true]);

        return User::create([
            'name' => $role->label(),
            'email' => $email,
            'password' => 'senha-segura',
            'setor_id' => $setor->id,
            'role' => $role,
        ]);
    }
}
