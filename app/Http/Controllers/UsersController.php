<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setor;
use App\Services\UserService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UsersController extends Controller
{
    protected UserService $userService;

    // Apenas CENTRAL pode gerenciar usuários
    public function __construct(UserService $userService)
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
        $this->userService = $userService;
    }

    public function index(): View
    {
        $users = User::with('setor')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.users.create', [
            'setores' => $setores,
        ]);
    }

    public function store(\App\Http\Requests\StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário criado com sucesso!');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.users.edit', [
            'user' => $user,
            'setores' => $setores,
        ]);
    }

    public function update(\App\Http\Requests\UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário atualizado com sucesso!');
    }

    public function destroy(User $user): RedirectResponse
    {
        $dadosUsuario = $user->toArray();
        $user->delete();

        AuditService::deletou('User', $user->id,
            "Usuário deletado: {$user->name} ({$user->email})",
            $dadosUsuario
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário deletado com sucesso!');
    }
}
