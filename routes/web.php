<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LancamentoSetorialController;
use App\Http\Controllers\PainelConferenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\ServidorController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PermissaoController;

// Rotas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Rota home - redireciona baseado no role
Route::get('/', [AuthController::class, 'home'])->middleware('auth')->name('home');

// Middleware de autenticação
Route::middleware(['auth'])->group(function () {
    
    // ===== SETORIAL (role: SETORIAL) =====
    Route::middleware(['role:SETORIAL'])->group(function () {
        Route::prefix('lancamentos')->name('lancamentos.')->group(function () {
            Route::get('/', [LancamentoSetorialController::class, 'index'])->name('index');
            Route::get('/create', [LancamentoSetorialController::class, 'create'])->name('create');
            Route::post('/', [LancamentoSetorialController::class, 'store'])->name('store');
            Route::get('/{lancamento}', [LancamentoSetorialController::class, 'show'])->name('show');
            Route::get('/{lancamento}/edit', [LancamentoSetorialController::class, 'edit'])->name('edit');
            Route::put('/{lancamento}', [LancamentoSetorialController::class, 'update'])->name('update');
            Route::delete('/{lancamento}', [LancamentoSetorialController::class, 'destroy'])->name('destroy');
        });
    });

    // ===== CENTRAL (role: CENTRAL) =====
    Route::middleware(['role:CENTRAL'])->group(function () {
        Route::prefix('painel')->name('painel.')->group(function () {
            Route::get('/', [PainelConferenciaController::class, 'index'])->name('index');
            Route::get('/{lancamento}', [PainelConferenciaController::class, 'show'])->name('show');
            Route::post('/{lancamento}/aprovar', [PainelConferenciaController::class, 'aprovar'])->name('aprovar');
            Route::post('/{lancamento}/rejeitar', [PainelConferenciaController::class, 'rejeitar'])->name('rejeitar');
            Route::post('/exportar', [PainelConferenciaController::class, 'exportar'])->name('exportar');
        });

        // ===== PAINEL ADMINISTRATIVO =====
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UsersController::class);
            Route::resource('setores', SetorController::class);
            Route::resource('servidores', ServidorController::class);
            Route::resource('eventos', EventoController::class);
            
            Route::get('permissoes', [PermissaoController::class, 'index'])->name('permissoes.index');
            Route::post('permissoes', [PermissaoController::class, 'store'])->name('permissoes.store');
            Route::delete('permissoes/{setor}/{evento}', [PermissaoController::class, 'destroy'])->name('permissoes.destroy');
            Route::post('permissoes/{setor}/{evento}/toggle', [PermissaoController::class, 'toggle'])->name('permissoes.toggle');
        });
    });
});
