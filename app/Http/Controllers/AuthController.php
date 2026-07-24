<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $usuario = User::where('email', $credentials['email'])->first();

        if ($usuario && (! $usuario->ativo || $usuario->estaBloqueado())) {
            AuditService::registrar('LOGIN_FALHOU', 'User', $usuario->id, 'Acesso negado para conta indisponível.');

            return $this->credenciaisInvalidas();
        }

        if (Auth::attempt([...$credentials, 'ativo' => true])) {
            $request->session()->regenerate();

            Auth::user()->forceFill([
                'tentativas_login_falhas' => 0,
                'bloqueado_ate' => null,
                'ultimo_login_em' => now(),
            ])->save();

            AuditService::login('Login realizado com sucesso');

            return redirect()->route('dashboard');
        }

        if ($usuario) {
            $tentativas = $usuario->tentativas_login_falhas + 1;
            $limite = max(1, (int) config('operations.authentication.max_failed_attempts'));
            $usuario->forceFill([
                'tentativas_login_falhas' => $tentativas >= $limite ? 0 : $tentativas,
                'bloqueado_ate' => $tentativas >= $limite
                    ? now()->addMinutes(max(1, (int) config('operations.authentication.lockout_minutes')))
                    : null,
            ])->save();
        }

        AuditService::registrar('LOGIN_FALHOU', 'User', $usuario?->id, 'Credenciais inválidas.');

        return $this->credenciaisInvalidas();
    }

    private function credenciaisInvalidas(): RedirectResponse
    {
        return back()->withErrors([
            'email' => 'As credenciais fornecidas não correspondem aos nossos registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditService::logout('Logout realizado');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function home(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }
}
