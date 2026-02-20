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
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\CompetenciaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\DelegacaoController;
use App\Http\Controllers\ImportacaoController;

// Rotas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Rota home - redireciona para dashboard
Route::get('/', [AuthController::class, 'home'])->middleware('auth')->name('home');

// Middleware de autenticação
Route::middleware(['auth'])->group(function () {
    
    // ===== DASHBOARD =====
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ===== PERFIL (acessível para todos os usuários autenticados) =====
    Route::prefix('perfil')->name('perfil.')->group(function () {
        Route::get('/', [PerfilController::class, 'show'])->name('show');
        Route::put('/', [PerfilController::class, 'update'])->name('update');
    });

    // ===== NOTIFICAÇÕES (acessível para todos os usuários autenticados) =====
    Route::prefix('notificacoes')->name('notificacoes.')->group(function () {
        Route::get('/', [NotificacaoController::class, 'index'])->name('index');
        Route::post('/{notificacao}/ler', [NotificacaoController::class, 'marcarComoLida'])->name('ler');
        Route::post('/ler-todas', [NotificacaoController::class, 'marcarTodasComoLidas'])->name('ler-todas');
    });
    
    // ===== SETORIAL (role: SETORIAL) =====
    Route::middleware(['role:SETORIAL'])->group(function () {
        Route::prefix('lancamentos')->name('lancamentos.')->group(function () {
            Route::get('/', [LancamentoSetorialController::class, 'index'])->name('index');
            Route::get('/create', [LancamentoSetorialController::class, 'create'])->name('create');
            Route::post('/', [LancamentoSetorialController::class, 'store'])->name('store');

            // Lixeira (soft delete)
            Route::get('/lixeira', [LancamentoSetorialController::class, 'lixeira'])->name('lixeira');
            Route::post('/lixeira/{id}/restaurar', [LancamentoSetorialController::class, 'restaurar'])->name('restaurar');

            // Aprovação setorial
            Route::post('/aprovar-setorial-lote', [LancamentoSetorialController::class, 'aprovarSetorialEmLote'])->name('aprovar-setorial-lote');

            // Importação CSV (antes de {lancamento})
            Route::get('/importar', [ImportacaoController::class, 'form'])->name('importar.form');
            Route::post('/importar', [ImportacaoController::class, 'importar'])->name('importar');

            // Delegações (antes de {lancamento})
            Route::prefix('delegacoes')->name('delegacoes.')->group(function () {
                Route::get('/', [DelegacaoController::class, 'index'])->name('index');
                Route::post('/', [DelegacaoController::class, 'store'])->name('store');
                Route::post('/{delegacao}/revogar', [DelegacaoController::class, 'revogar'])->name('revogar');
            });

            Route::get('/{lancamento}', [LancamentoSetorialController::class, 'show'])->name('show');
            Route::get('/{lancamento}/edit', [LancamentoSetorialController::class, 'edit'])->name('edit');
            Route::put('/{lancamento}', [LancamentoSetorialController::class, 'update'])->name('update');
            Route::delete('/{lancamento}', [LancamentoSetorialController::class, 'destroy'])->name('destroy');
            Route::post('/{lancamento}/aprovar-setorial', [LancamentoSetorialController::class, 'aprovarSetorial'])->name('aprovar-setorial');


        });
    });

    // ===== CENTRAL (role: CENTRAL) =====
    Route::middleware(['role:CENTRAL'])->group(function () {
        Route::prefix('painel')->name('painel.')->group(function () {
            Route::get('/', [PainelConferenciaController::class, 'index'])->name('index');
            Route::post('/exportar', [PainelConferenciaController::class, 'exportar'])->name('exportar');
            Route::post('/aprovar-lote', [PainelConferenciaController::class, 'aprovarEmLote'])->name('aprovar-lote');
            Route::get('/{lancamento}', [PainelConferenciaController::class, 'show'])
                ->whereNumber('lancamento')
                ->name('show');
            Route::post('/{lancamento}/aprovar', [PainelConferenciaController::class, 'aprovar'])
                ->whereNumber('lancamento')
                ->name('aprovar');
            Route::post('/{lancamento}/rejeitar', [PainelConferenciaController::class, 'rejeitar'])
                ->whereNumber('lancamento')
                ->name('rejeitar');
            Route::post('/{lancamento}/estornar', [PainelConferenciaController::class, 'estornar'])
                ->whereNumber('lancamento')
                ->name('estornar');
        });

        // ===== PAINEL ADMINISTRATIVO =====
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UsersController::class);
            Route::resource('setores', SetorController::class)->parameters(['setores' => 'setor']);
            Route::resource('servidores', ServidorController::class)->except('destroy');
            Route::resource('eventos', EventoController::class);
            Route::get('permissoes', [PermissaoController::class, 'index'])->name('permissoes.index');
            Route::post('permissoes', [PermissaoController::class, 'store'])->name('permissoes.store');
            Route::delete('permissoes/{setor}/{evento}', [PermissaoController::class, 'destroy'])->name('permissoes.destroy');
            Route::patch('permissoes/{setor}/{evento}/toggle', [PermissaoController::class, 'toggle'])->name('permissoes.toggle');
            Route::delete('servidores/{servidor}/desativar', [ServidorController::class, 'destroy'])->name('servidores.destroy');
            Route::post('servidores/{servidor}/ativar', [ServidorController::class, 'ativar'])->name('servidores.ativar');
            Route::get('servidores/{servidor}/transferir', [ServidorController::class, 'formTransferir'])->name('servidores.transferir.form');
            Route::post('servidores/{servidor}/transferir', [ServidorController::class, 'transferir'])->name('servidores.transferir');
            Route::get('servidores/{servidor}/desligar', [ServidorController::class, 'formDesligar'])->name('servidores.desligar.form');
            Route::post('servidores/{servidor}/desligar', [ServidorController::class, 'desligar'])->name('servidores.desligar');

            // ===== AUDITORIA =====
            Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('audit/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

            // ===== COMPETÊNCIAS =====
            Route::prefix('competencias')->name('competencias.')->group(function () {
                Route::get('/', [CompetenciaController::class, 'index'])->name('index');
                Route::post('/', [CompetenciaController::class, 'store'])->name('store');
                Route::post('/{competencia}/fechar', [CompetenciaController::class, 'fechar'])->name('fechar');
                Route::post('/{competencia}/reabrir', [CompetenciaController::class, 'reabrir'])->name('reabrir');
            });

            // ===== RELATÓRIOS =====
            Route::prefix('relatorios')->name('relatorios.')->group(function () {
                Route::get('/resumo', [RelatorioController::class, 'resumo'])->name('resumo');
                Route::get('/comparativo', [RelatorioController::class, 'comparativo'])->name('comparativo');
                Route::get('/folha-espelho', [RelatorioController::class, 'folhaEspelho'])->name('folha-espelho');
                Route::get('/exportar-csv', [RelatorioController::class, 'exportarCsv'])->name('exportar-csv');
            });
        });
    });
});

