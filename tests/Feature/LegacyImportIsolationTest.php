<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImportacaoService;
use Tests\TestCase;

class LegacyImportIsolationTest extends TestCase
{
    public function test_spreadsheet_import_is_not_an_operational_route(): void
    {
        $router = app('router');

        $this->assertFalse($router->has('lancamentos.importar.form'));
        $this->assertFalse($router->has('lancamentos.importar'));
        $this->assertFalse($router->has('lancamentos.importar.preview'));
        $this->assertFalse($router->has('lancamentos.importar.confirmar'));
        $this->assertFalse($router->has('ocorrencias.importar.form'));
        $this->assertFalse($router->has('ocorrencias.importar.store'));
        $this->assertFalse($router->has('ocorrencias.importar.preview'));
        $this->assertFalse($router->has('ocorrencias.importar.confirmar'));
    }

    public function test_legacy_service_refuses_programmatic_execution(): void
    {
        $this->assertFalse(config('operations.legacy.spreadsheet_import_enabled'));
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Use o lancamento nativo do sistema');

        app(ImportacaoService::class)->analisarCsv(
            'arquivo-inexistente.csv',
            1,
            new User,
        );
    }
}
