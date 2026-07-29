<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OperationalReadinessService
{
    /** @return array{ready: bool, checks: array<string, array{ok: bool, message: string}>} */
    public function verificar(bool $exigirConfiguracaoProducao = false): array
    {
        $checks = [
            'mysql' => $this->executar(function (): string {
                if (DB::connection()->getDriverName() !== 'mysql') {
                    throw new \RuntimeException('O driver ativo não é MySQL.');
                }
                DB::selectOne('SELECT 1 AS ready');

                return 'Conexão MySQL disponível.';
            }),
            'migrations' => $this->executar(function (): string {
                $migrator = app('migrator');
                if (! $migrator->repositoryExists()) {
                    throw new \RuntimeException('A tabela de migrations ainda não existe.');
                }
                $arquivos = $migrator->getMigrationFiles(database_path('migrations'));
                $pendentes = array_diff(array_keys($arquivos), $migrator->getRepository()->getRan());
                if ($pendentes !== []) {
                    throw new \RuntimeException(count($pendentes).' migration(s) pendente(s).');
                }

                return 'Schema atualizado.';
            }),
            'cache' => $this->executar(function (): string {
                $chave = config('operations.readiness.cache_key').':'.Str::uuid();
                Cache::put($chave, 'ok', 30);
                if (Cache::get($chave) !== 'ok') {
                    throw new \RuntimeException('O valor de teste não foi recuperado do cache.');
                }
                Cache::forget($chave);

                return 'Cache disponível.';
            }),
            'private_storage' => $this->executar(function (): string {
                $disco = Storage::disk('local');
                $caminho = trim(config('operations.readiness.storage_path'), '/').'.'.Str::uuid();
                $raiz = realpath(config('filesystems.disks.local.root')) ?: config('filesystems.disks.local.root');
                if (! str_starts_with(str_replace('\\', '/', $raiz), str_replace('\\', '/', storage_path('app/private')))) {
                    throw new \RuntimeException('O disco local não aponta para o armazenamento privado esperado.');
                }
                try {
                    if (! $disco->put($caminho, 'ready') || $disco->get($caminho) !== 'ready') {
                        throw new \RuntimeException('Não foi possível gravar e ler o arquivo de teste.');
                    }
                } finally {
                    $disco->delete($caminho);
                }

                return 'Armazenamento privado disponível.';
            }),
        ];

        if ($exigirConfiguracaoProducao || app()->environment('production')) {
            $checks['production_configuration'] = $this->validarConfiguracaoProducao();
        }

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function validarConfiguracaoProducao(): array
    {
        $erros = [];
        if (config('app.debug')) {
            $erros[] = 'APP_DEBUG deve estar desativado';
        }
        $chave = (string) config('app.key');
        $chaveBinaria = str_starts_with($chave, 'base64:')
            ? base64_decode(substr($chave, 7), true)
            : $chave;
        if (! is_string($chaveBinaria) || ! Encrypter::supported($chaveBinaria, config('app.cipher'))) {
            $erros[] = 'APP_KEY não possui tamanho ou formato válido';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $erros[] = 'APP_URL deve usar HTTPS';
        }
        if (config('queue.default') !== 'database' || config('queue.failed.database') !== 'mysql') {
            $erros[] = 'fila e falhas devem usar MySQL';
        }
        if (config('session.default') !== 'database' || ! config('session.secure')) {
            $erros[] = 'sessões devem usar MySQL e cookie seguro';
        }
        if (config('cache.default') !== 'database') {
            $erros[] = 'cache compartilhado deve usar MySQL';
        }
        if (config('filesystems.default') !== 'local') {
            $erros[] = 'evidências devem usar o disco privado local';
        }
        if (! config('operations.backup.encryption_key_configured')) {
            $erros[] = 'chave de criptografia dos backups não configurada';
        }

        return $erros === []
            ? ['ok' => true, 'message' => 'Configuração de produção coerente.']
            : ['ok' => false, 'message' => implode('; ', $erros).'.'];
    }

    /** @return array{ok: bool, message: string} */
    private function executar(callable $check): array
    {
        try {
            return ['ok' => true, 'message' => $check()];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }
}
