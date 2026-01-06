<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setor;
use App\Models\Servidor;

class TestServidorSeeder extends Seeder
{
    public function run(): void
    {
        $setor = Setor::where('ativo', true)->first() ?: Setor::create([
            'nome' => 'SETOR TESTE',
            'sigla' => 'TEST',
            'ativo' => true,
        ]);

        $srv = Servidor::create([
            'matricula' => 'AUTO' . time(),
            'nome' => 'Teste Automático',
            'setor_id' => $setor->id,
            'origem_registro' => 'MANUAL',
            'ativo' => true,
        ]);

        $this->command->info('SERVIDOR_ID=' . $srv->id);
    }
}
