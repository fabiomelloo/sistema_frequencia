<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Models\Notificacao;

class ShareNotificacoes
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $notificacoesNaoLidas = Notificacao::where('user_id', $userId)
                ->whereNull('lida_em')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Usa count() da query base (1 query extra apenas se houver mais de 10)
            $contadorNotificacoes = $notificacoesNaoLidas->count() < 10
                ? $notificacoesNaoLidas->count()
                : Notificacao::where('user_id', $userId)->whereNull('lida_em')->count();

            View::share('notificacoesNaoLidas', $notificacoesNaoLidas);
            View::share('contadorNotificacoes', $contadorNotificacoes);
        } else {
            View::share('notificacoesNaoLidas', collect());
            View::share('contadorNotificacoes', 0);
        }

        return $next($request);
    }
}
