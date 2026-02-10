<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerfilRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PerfilController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $user->load('setor');

        return view('perfil.show', [
            'user' => $user,
        ]);
    }

    public function update(UpdatePerfilRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Atualizar senha apenas se foi informada (cast 'hashed' no Model faz o hash)
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('perfil.show')
            ->with('success', 'Perfil atualizado com sucesso!');
    }
}
