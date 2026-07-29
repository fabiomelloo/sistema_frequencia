<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Setor;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UsersController extends Controller
{
    protected UserService $userService;

    // CENTRAL consulta usuários; somente ADMIN pode alterar contas.
    public function __construct(UserService $userService)
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL|ADMIN');
        $this->userService = $userService;
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('setor')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.users.create', [
            'setores' => $setores,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário criado com sucesso!');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.users.edit', [
            'user' => $user,
            'setores' => $setores,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário atualizado com sucesso!');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->desativar($user, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário desativado. O histórico foi preservado.');
    }

    public function ativar(User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->userService->ativar($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário reativado com sucesso.');
    }
}
