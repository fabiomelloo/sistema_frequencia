<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $rolesPermitidas = explode('|', $role);

        if (!in_array(auth()->user()->role->value, $rolesPermitidas)) {
            abort(403, 'Acesso negado.');
        }

        return $next($request);
    }
}
