<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);

// Rotas simples
if ($request_path === '' || $request_path === '/') {
    include BASE_PATH . '/public/demo.php';
} else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Página não encontrada</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="text-center">
                <h1 class="display-1">404</h1>
                <p class="lead">Página não encontrada</p>
                <a href="/" class="btn btn-primary">Voltar ao início</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
