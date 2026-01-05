<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UsersController extends Controller
{
    // Apenas CENTRAL pode gerenciar usuários
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
    }

    public function index(): View
    {
        $users = User::with('setor')
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('users.create', [
            'setores' => $setores,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'setor_id' => ['required', 'exists:setores,id'],
            'role' => ['required', 'in:SETORIAL,CENTRAL'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'setor_id' => $validated['setor_id'],
            'role' => $validated['role'],
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuário criado com sucesso!');
    }

    public function show(User $user): View
    {
        return view('users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('users.edit', [
            'user' => $user,
            'setores' => $setores,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'setor_id' => ['required', 'exists:setores,id'],
            'role' => ['required', 'in:SETORIAL,CENTRAL'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'setor_id' => $validated['setor_id'],
            'role' => $validated['role'],
        ]);

        if ($validated['password'] ?? null) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuário atualizado com sucesso!');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuário deletado com sucesso!');
    }
}
