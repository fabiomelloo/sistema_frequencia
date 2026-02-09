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

    public function store(\App\Http\Requests\StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['password'] = bcrypt($validated['password']);

        User::create($validated);

        return redirect()
            ->route('admin.users.index')
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

    public function update(\App\Http\Requests\UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        
        // Remover password do array se estiver vazio
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

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
