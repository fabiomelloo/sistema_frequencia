<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isCentral()) {
                return redirect()->route('painel.index');
            }
            return redirect()->route('lancamentos.index');
        }

        return $next($request);
    }
}
