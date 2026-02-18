<?php

namespace App\Console\Commands;

use App\Models\Delegacao;
use Illuminate\Console\Command;

class DesativarDelegacoesVencidas extends Command
{
    protected $signature = 'delegacoes:desativar-vencidas';
    protected $description = 'Desativa delegações cuja data_fim já passou';

    public function handle(): int
    {
        $vencidas = Delegacao::where('ativa', true)
            ->where('data_fim', '<', now()->startOfDay())
            ->get();

        $total = $vencidas->count();

        foreach ($vencidas as $delegacao) {
            $delegacao->ativa = false;
            $delegacao->save();
        }

        $this->info("{$total} delegação(ões) vencida(s) desativada(s).");

        return Command::SUCCESS;
    }
}
