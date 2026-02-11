<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Database Status...\n";

try {
    DB::connection()->getPdo();
    echo "Database connection: OK\n";
} catch (\Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration Status:\n";
Artisan::call('migrate:status');
echo Artisan::output();

echo "\nChecking Tables:\n";
$tables = ['users', 'setores', 'servidores', 'eventos_folha', 'lancamentos_setoriais', 'competencias', 'lotacao_historico', 'delegacoes', 'configuracoes'];
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "Table '$table': OK\n";
    } else {
        echo "Table '$table': MISSING\n";
    }
}

echo "\nChecking Columns in 'lancamentos_setoriais':\n";
$columns = ['competencia', 'conferido_setorial_por', 'deleted_at'];
foreach ($columns as $column) {
    if (Schema::hasColumn('lancamentos_setoriais', $column)) {
        echo "Column '$column': OK\n";
    } else {
        echo "Column '$column': MISSING\n";
    }
}
