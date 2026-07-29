<?php

namespace App\Console\Commands;

use App\Services\OperationalReadinessService;
use Illuminate\Console\Command;

class VerificarProntidaoSistema extends Command
{
    protected $signature = 'sistema:verificar-prontidao {--producao : Exigir todas as configurações obrigatórias de produção}';

    protected $description = 'Verifica MySQL, migrations, cache, armazenamento privado e configuração segura de produção';

    public function handle(OperationalReadinessService $service): int
    {
        $resultado = $service->verificar($this->option('producao'));
        $linhas = collect($resultado['checks'])->map(fn (array $check, string $nome): array => [
            $nome,
            $check['ok'] ? 'OK' : 'FALHA',
            $check['message'],
        ])->values()->all();

        $this->table(['Verificação', 'Estado', 'Detalhe'], $linhas);
        $resultado['ready']
            ? $this->info('Sistema pronto para o modo verificado.')
            : $this->error('Sistema ainda não está pronto para o modo verificado.');

        return $resultado['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
