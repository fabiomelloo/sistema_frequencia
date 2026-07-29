<?php

namespace Tests\Feature;

use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_records_form_an_immutable_verifiable_chain(): void
    {
        $primeiro = AuditService::registrar('TESTE_1', 'Sistema', null, 'Primeiro registro');
        $segundo = AuditService::registrar('TESTE_2', 'Sistema', null, 'Segundo registro');

        $this->assertNotNull($primeiro->uuid);
        $this->assertNotNull($primeiro->hash_registro);
        $this->assertNull($primeiro->hash_anterior);
        $this->assertSame($primeiro->hash_registro, $segundo->hash_anterior);
        $this->artisan('auditoria:verificar-integridade')->assertSuccessful();

        $this->expectException(LogicException::class);
        $primeiro->update(['descricao' => 'Alteracao indevida']);
    }

    public function test_verifier_detects_direct_database_tampering(): void
    {
        $registro = AuditService::registrar('TESTE', 'Sistema', null, 'Conteudo original');
        DB::table('audit_logs')->where('id', $registro->id)->update(['descricao' => 'Adulterado']);

        $this->artisan('auditoria:verificar-integridade')
            ->expectsOutputToContain('Falha de integridade')
            ->assertFailed();
    }
}
