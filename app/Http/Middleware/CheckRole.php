<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! auth()->check()) {
            return redirect('/login');
        }

        $rolesPermitidas = explode('|', $role);

        $userRole = auth()->user()->role;
        $userRoleValue = $userRole instanceof \UnitEnum ? $userRole->value : $userRole;

        if (! in_array($userRoleValue, $rolesPermitidas)) {
            abort(403, 'Acesso negado.');
        }

        return $next($request);
    }
}
