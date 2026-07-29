<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LimparArquivosExportados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sistema:limpar-exportacoes {--dias=7 : Manter arquivos dos últimos N dias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove arquivos TXT de exportação de folha com mais de N dias do storage local.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        if ($dias < 1) {
            $this->error('A retenção deve ser de pelo menos um dia.');

            return self::FAILURE;
        }

        $dataCorte = now()->subDays($dias);

        $this->info("Removendo arquivos exportados com mais de {$dias} dias (criados antes de {$dataCorte->format('d/m/Y')})...");

        // Lista todos os arquivos no diretório de exportações
        $arquivos = Storage::disk('local')->files('exportacoes');
        $removidos = 0;
        $erros = 0;

        foreach ($arquivos as $arquivo) {
            // Apenas arquivos .txt gerados pela exportação de folha
            if (! str_ends_with($arquivo, '.txt')) {
                continue;
            }

            $ultimaModificacao = Storage::disk('local')->lastModified($arquivo);

            if ($ultimaModificacao && $ultimaModificacao < $dataCorte->timestamp) {
                try {
                    Storage::disk('local')->delete($arquivo);
                    $removidos++;
                    $this->line("  Removido: {$arquivo}");
                } catch (\Exception $e) {
                    $erros++;
                    $this->warn("  Erro ao remover {$arquivo}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Limpeza concluída: {$removidos} arquivo(s) removido(s), {$erros} erro(s).");

        return self::SUCCESS;
    }
}
