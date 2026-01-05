<?php

/*
|--------------------------------------------------------------------------
| Sistema de Frequência - Tela de Apresentação
|--------------------------------------------------------------------------
|
| Esta é uma versão simplificada para demonstração.
| Para usar a aplicação completa, instale via Composer:
| composer install
|
*/

// Definir path base
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Detectar requisição
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_path = str_replace('/index.php', '', $request_uri);
$request_path = ltrim($request_path, '/');

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
