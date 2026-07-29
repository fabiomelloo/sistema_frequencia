<?php

namespace Database\Seeders;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $setor = Setor::firstOrCreate(
            ['sigla' => 'EX'],
            [
                'nome' => 'Setor Exemplo',
                'ativo' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => 'ADMIN',
                'setor_id' => $setor->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'setorial@sistema.com'],
            [
                'name' => 'Setorial',
                'password' => 'password',
                'role' => 'SETORIAL',
                'setor_id' => $setor->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'central@sistema.com'],
            [
                'name' => 'Central',
                'password' => 'password',
                'role' => 'CENTRAL',
                'setor_id' => $setor->id,
            ]
        );

    }
}
