<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setor;
use App\Models\EventoFolha;
use App\Models\Servidor;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Garantir que exite um setor 'Setor Exemplo'
        $setor = Setor::firstOrCreate(
            ['sigla' => 'EX'],
            ['nome' => 'Setor Exemplo', 'ativo' => true]
        );

        // 2. Garantir que existem Eventos
        $evento1 = EventoFolha::firstOrCreate(
            ['codigo_evento' => '1001'],
            [
                'descricao' => 'Hora Extra 50%',
                'tipo_evento' => \App\Enums\TipoEvento::GRATIFICACAO,
                'ativo' => true,
                'exige_dias' => true,
                'exige_valor' => true,
            ]
        );
        $evento2 = EventoFolha::firstOrCreate(
            ['codigo_evento' => '2001'],
            [
                'descricao' => 'Falta Injustificada',
                'tipo_evento' => \App\Enums\TipoEvento::OUTROS,
                'ativo' => true,
                'exige_dias' => true,
            ]
        );

        // 3. Vincular Eventos ao Setor (tabela pivo)
        // syncWithoutDetaching evita duplicatas sem remover os existentes
        $setor->eventosPermitidos()->syncWithoutDetaching([
            $evento1->id => ['ativo' => true],
            $evento2->id => ['ativo' => true],
        ]);

        // 4. Criar Servidores no Setor
        if (Servidor::where('setor_id', $setor->id)->count() === 0) {
            Servidor::create([
                'nome' => 'João da Silva',
                'matricula' => '111222',
                'setor_id' => $setor->id,
                'ativo' => true,
                'origem_registro' => 'SEEDER',
            ]);
            Servidor::create([
                'nome' => 'Maria Oliveira',
                'matricula' => '333444',
                'setor_id' => $setor->id,
                'ativo' => true,
                'origem_registro' => 'SEEDER',
            ]);
        }
    }
}
