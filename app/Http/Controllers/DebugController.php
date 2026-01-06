<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DebugController extends Controller
{
    public function forms()
    {
        return view('debug.forms');
    }

    public function post(Request $request): JsonResponse
    {
        return response()->json([
            'method' => $request->method(),
            'ok' => true,
            'csrf_token' => csrf_token(),
            'input' => $request->all(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        return response()->json([
            'method' => $request->method(),
            'ok' => true,
            'csrf_token' => csrf_token(),
            'input' => $request->all(),
        ]);
    }
}
