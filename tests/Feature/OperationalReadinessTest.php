<?php

namespace Tests\Feature;

use App\Services\OperationalReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_exposes_only_check_states(): void
    {
        $response = $this->getJson(route('health.ready'));

        $response
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-XSS-Protection', '0')
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.mysql', true)
            ->assertJsonPath('checks.migrations', true)
            ->assertJsonPath('checks.cache', true)
            ->assertJsonPath('checks.private_storage', true)
            ->assertJsonMissingPath('checks.mysql.message');
    }

    public function test_production_check_rejects_insecure_or_incomplete_configuration(): void
    {
        config([
            'app.debug' => true,
            'app.key' => 'invalida',
            'app.url' => 'http://localhost',
            'queue.default' => 'sync',
            'session.default' => 'array',
            'session.secure' => false,
            'cache.default' => 'array',
            'operations.backup.encryption_key_configured' => false,
        ]);

        $resultado = app(OperationalReadinessService::class)->verificar(true);

        $this->assertFalse($resultado['ready']);
        $this->assertFalse($resultado['checks']['production_configuration']['ok']);
        $this->assertStringContainsString('APP_KEY', $resultado['checks']['production_configuration']['message']);
    }

    public function test_production_check_accepts_required_mysql_configuration(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.url' => 'https://frequencia.exemplo.gov.br',
            'queue.default' => 'database',
            'queue.failed.database' => 'mysql',
            'session.default' => 'database',
            'session.secure' => true,
            'cache.default' => 'database',
            'filesystems.default' => 'local',
            'operations.backup.encryption_key_configured' => true,
        ]);

        $resultado = app(OperationalReadinessService::class)->verificar(true);

        $this->assertTrue($resultado['ready']);
        $this->assertTrue($resultado['checks']['production_configuration']['ok']);
    }

    public function test_readiness_command_succeeds_for_local_environment(): void
    {
        $this->artisan('sistema:verificar-prontidao')
            ->expectsOutputToContain('Sistema pronto para o modo verificado.')
            ->assertSuccessful();
    }
}
