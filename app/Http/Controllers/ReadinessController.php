<?php

namespace App\Http\Controllers;

use App\Services\OperationalReadinessService;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(OperationalReadinessService $service): JsonResponse
    {
        $resultado = $service->verificar();

        return response()->json([
            'status' => $resultado['ready'] ? 'ready' : 'not_ready',
            'checks' => collect($resultado['checks'])->map(fn (array $check): bool => $check['ok']),
            'checked_at' => now()->toIso8601String(),
        ], $resultado['ready'] ? 200 : 503);
    }
}
