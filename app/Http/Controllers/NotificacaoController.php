<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class NotificacaoController extends Controller
{
    public function index(): View
    {
        $notificacoes = Notificacao::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('notificacoes.index', [
            'notificacoes' => $notificacoes,
        ]);
    }

    public function marcarComoLida(Notificacao $notificacao): RedirectResponse
    {
        if ($notificacao->user_id !== auth()->id()) {
            abort(403);
        }

        $notificacao->marcarComoLida();

        if ($notificacao->link) {
            return redirect($notificacao->link);
        }

        return redirect()->back();
    }

    public function marcarTodasComoLidas(): RedirectResponse
    {
        Notificacao::where('user_id', auth()->id())
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        return redirect()->back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }
}
