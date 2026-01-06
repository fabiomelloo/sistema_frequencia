<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create admin user if not exists

$email = 'admin@example.com';
if (\App\Models\User::where('email', $email)->exists()) {
    echo "User already exists: {$email}\n";
    exit(0);
}

// Ensure at least one setor exists and get its id
$setor = \App\Models\Setor::first();
if (! $setor) {
    $setor = \App\Models\Setor::create(['nome' => 'Administrativo', 'sigla' => 'ADM', 'ativo' => true]);
    echo "Created setor id={$setor->id}\n";
}

$user = \App\Models\User::create([
    'name' => 'Administrador',
    'email' => $email,
    'password' => \Illuminate\Support\Facades\Hash::make('admin1234'),
    'role' => 'CENTRAL',
    'setor_id' => $setor->id,
]);

echo "Created user id={$user->id} email={$user->email}\n";
