<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Util helpers for simple static route handling below
define('BASE_PATH', realpath(__DIR__ . '/..'));
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

// Simple static routes run BEFORE Laravel to avoid double responses
if ($request_path === '' || $request_path === '/') {
    include BASE_PATH . '/public/demo.php';
    exit;
}

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
