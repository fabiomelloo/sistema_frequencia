<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetenciaController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegacaoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\EvidenciaDocumentalController;
use App\Http\Controllers\FolhaFrequenciaController;
use App\Http\Controllers\FolhaFrequenciaItemController;
use App\Http\Controllers\HistoricoFuncionalController;
use App\Http\Controllers\LancamentoSetorialController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\OcorrenciaFrequenciaController;
use App\Http\Controllers\PainelConferenciaController;
use App\Http\Controllers\PainelFrequenciaController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PermissaoController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ServidorController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// Rotas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::get('/health/ready', ReadinessController::class)->name('health.ready')->middleware('throttle:30,1');

// Rota home - redireciona para dashboard
Route::get('/', [AuthController::class, 'home'])->middleware('auth')->name('home');

// Middleware de autenticação
Route::middleware(['auth'])->group(function () {

    Route::get('/evidencias/{evidencia}/download', [EvidenciaDocumentalController::class, 'download'])
        ->whereNumber('evidencia')->name('evidencias.download')->middleware('throttle:60,1');

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

    // ===== SETORIAL (role: SETORIAL ou GESTOR) =====
    Route::middleware(['role:SETORIAL|GESTOR'])->group(function () {
        Route::prefix('frequencia')->name('frequencia.')->group(function () {
            Route::get('/', [FolhaFrequenciaController::class, 'index'])->name('index');
            Route::post('/', [FolhaFrequenciaController::class, 'store'])->name('store');
            Route::get('/{folha}', [FolhaFrequenciaController::class, 'show'])->whereNumber('folha')->name('show');
            Route::put('/{folha}/servidores/{item}', [FolhaFrequenciaController::class, 'updateServidor'])
                ->whereNumber(['folha', 'item'])->name('servidores.update');
            Route::post('/{folha}/servidores/{item}/itens', [FolhaFrequenciaItemController::class, 'store'])
                ->whereNumber(['folha', 'item'])->name('servidores.itens.store');
            Route::put('/{folha}/servidores/{item}/itens/{frequenciaItem}', [FolhaFrequenciaItemController::class, 'update'])
                ->whereNumber(['folha', 'item', 'frequenciaItem'])->name('servidores.itens.update');
            Route::delete('/{folha}/servidores/{item}/itens/{frequenciaItem}', [FolhaFrequenciaItemController::class, 'destroy'])
                ->whereNumber(['folha', 'item', 'frequenciaItem'])->name('servidores.itens.destroy');
            Route::post('/{folha}/servidores/{item}/itens/{frequenciaItem}/evidencias', [EvidenciaDocumentalController::class, 'storeItem'])
                ->whereNumber(['folha', 'item', 'frequenciaItem'])->name('servidores.itens.evidencias.store')->middleware('throttle:20,1');
            Route::post('/{folha}/finalizar', [FolhaFrequenciaController::class, 'finalizar'])
                ->whereNumber('folha')->name('finalizar');
            Route::post('/{folha}/reabrir', [FolhaFrequenciaController::class, 'reabrir'])
                ->whereNumber('folha')->name('reabrir');
        });

        Route::resource('ocorrencias', OcorrenciaFrequenciaController::class);
        Route::post('/ocorrencias/{ocorrencia}/evidencias', [EvidenciaDocumentalController::class, 'storeOcorrencia'])
            ->whereNumber('ocorrencia')->name('ocorrencias.evidencias.store')->middleware('throttle:20,1');
        Route::delete('/evidencias/{evidencia}', [EvidenciaDocumentalController::class, 'destroy'])
            ->whereNumber('evidencia')->name('evidencias.destroy')->middleware('throttle:30,1');

        Route::prefix('lancamentos')->name('lancamentos.')->group(function () {
            Route::get('/', [LancamentoSetorialController::class, 'index'])->name('index');
            Route::get('/create', [LancamentoSetorialController::class, 'create'])->name('create');
            Route::post('/', [LancamentoSetorialController::class, 'store'])->name('store');

            // Lixeira (soft delete)
            Route::get('/lixeira', [LancamentoSetorialController::class, 'lixeira'])->name('lixeira');
            Route::post('/lixeira/{id}/restaurar', [LancamentoSetorialController::class, 'restaurar'])->name('restaurar');

            // Aprovação setorial
            Route::post('/aprovar-setorial-lote', [LancamentoSetorialController::class, 'aprovarSetorialEmLote'])->name('aprovar-setorial-lote')->middleware('throttle:30,1');

            // Delegações (antes de {lancamento})
            Route::prefix('delegacoes')->name('delegacoes.')->middleware('role:SETORIAL')->group(function () {
                Route::get('/', [DelegacaoController::class, 'index'])->name('index');
                Route::post('/', [DelegacaoController::class, 'store'])->name('store');
                Route::post('/{delegacao}/revogar', [DelegacaoController::class, 'revogar'])->name('revogar');
            });

            Route::get('/{lancamento}', [LancamentoSetorialController::class, 'show'])->name('show');
            Route::get('/{lancamento}/edit', [LancamentoSetorialController::class, 'edit'])->name('edit');
            Route::put('/{lancamento}', [LancamentoSetorialController::class, 'update'])->name('update');
            Route::delete('/{lancamento}', [LancamentoSetorialController::class, 'destroy'])->name('destroy');
            Route::post('/{lancamento}/aprovar-setorial', [LancamentoSetorialController::class, 'aprovarSetorial'])->name('aprovar-setorial')->middleware('throttle:60,1');
            Route::post('/{lancamento}/cancelar', [LancamentoSetorialController::class, 'cancelar'])->name('cancelar');
            Route::post('/{lancamento}/solicitar-estorno', [LancamentoSetorialController::class, 'solicitarEstorno'])->name('solicitar-estorno');

        });
    });

    // ===== CENTRAL (role: CENTRAL ou ADMIN) — somente painel de conferência =====
    Route::middleware(['role:CENTRAL|ADMIN'])->group(function () {
        Route::prefix('painel-frequencias')->name('painel-frequencias.')->group(function () {
            Route::get('/', [PainelFrequenciaController::class, 'index'])->name('index');
            Route::get('/{folha}', [PainelFrequenciaController::class, 'show'])->whereNumber('folha')->name('show');
            Route::post('/{folha}/servidores/{item}/conferir', [PainelFrequenciaController::class, 'conferirServidor'])
                ->whereNumber(['folha', 'item'])->name('servidores.conferir')->middleware('throttle:120,1');
            Route::post('/{folha}/servidores/{item}/evidencias/{evidencia}/revisar', [EvidenciaDocumentalController::class, 'revisar'])
                ->whereNumber(['folha', 'item', 'evidencia'])->name('evidencias.revisar')->middleware('throttle:120,1');
            Route::post('/{folha}/aprovar', [PainelFrequenciaController::class, 'aprovar'])
                ->whereNumber('folha')->name('aprovar')->middleware('throttle:60,1');
            Route::post('/{folha}/devolver', [PainelFrequenciaController::class, 'devolver'])
                ->whereNumber('folha')->name('devolver')->middleware('throttle:30,1');
        });

        Route::prefix('painel')->name('painel.')->group(function () {
            Route::get('/', [PainelConferenciaController::class, 'index'])->name('index');
            Route::post('/exportar', [PainelConferenciaController::class, 'exportar'])->name('exportar')->middleware('throttle:10,1');
            Route::post('/aprovar-lote', [PainelConferenciaController::class, 'aprovarEmLote'])->name('aprovar-lote')->middleware('throttle:30,1');
            Route::get('/{lancamento}', [PainelConferenciaController::class, 'show'])
                ->whereNumber('lancamento')
                ->name('show');
            Route::post('/{lancamento}/aprovar', [PainelConferenciaController::class, 'aprovar'])
                ->whereNumber('lancamento')
                ->name('aprovar')->middleware('throttle:60,1');
            Route::post('/{lancamento}/rejeitar', [PainelConferenciaController::class, 'rejeitar'])
                ->whereNumber('lancamento')
                ->name('rejeitar');
            Route::post('/{lancamento}/estornar', [PainelConferenciaController::class, 'estornar'])
                ->whereNumber('lancamento')
                ->name('estornar');
            Route::post('/{lancamento}/recusar-estorno', [PainelConferenciaController::class, 'recusarEstorno'])
                ->whereNumber('lancamento')
                ->name('recusar-estorno');
        });
    });

    // ===== PAINEL ADMINISTRATIVO (role: CENTRAL ou ADMIN) =====
    Route::middleware(['role:CENTRAL|ADMIN'])->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UsersController::class)->except('show');
            Route::patch('users/{user}/ativar', [UsersController::class, 'ativar'])
                ->whereNumber('user')->name('users.ativar');
            Route::resource('setores', SetorController::class)->parameters(['setores' => 'setor']);
            Route::resource('eventos', EventoController::class);
            Route::get('permissoes', [PermissaoController::class, 'index'])->name('permissoes.index');
            Route::post('permissoes', [PermissaoController::class, 'store'])->name('permissoes.store');
            Route::delete('permissoes/{setor}/{evento}', [PermissaoController::class, 'destroy'])->name('permissoes.destroy');
            Route::patch('permissoes/{setor}/{evento}/toggle', [PermissaoController::class, 'toggle'])->name('permissoes.toggle');
            // Servidores (sem route resource para evitar conflito de nomes com desativar)
            Route::get('servidores', [ServidorController::class, 'index'])->name('servidores.index');
            Route::get('servidores/create', [ServidorController::class, 'create'])->name('servidores.create');
            Route::post('servidores', [ServidorController::class, 'store'])->name('servidores.store');
            Route::get('servidores/{servidor}', [ServidorController::class, 'show'])->name('servidores.show');
            Route::get('servidores/{servidor}/edit', [ServidorController::class, 'edit'])->name('servidores.edit');
            Route::put('servidores/{servidor}', [ServidorController::class, 'update'])->name('servidores.update');
            Route::delete('servidores/{servidor}/desativar', [ServidorController::class, 'destroy'])->name('servidores.destroy');
            Route::post('servidores/{servidor}/ativar', [ServidorController::class, 'ativar'])->name('servidores.ativar');
            Route::get('servidores/{servidor}/transferir', [ServidorController::class, 'formTransferir'])->name('servidores.transferir.form');
            Route::post('servidores/{servidor}/transferir', [ServidorController::class, 'transferir'])->name('servidores.transferir');
            Route::get('servidores/{servidor}/desligar', [ServidorController::class, 'formDesligar'])->name('servidores.desligar.form');
            Route::post('servidores/{servidor}/desligar', [ServidorController::class, 'desligar'])->name('servidores.desligar');
            Route::get('servidores/{servidor}/historico-funcional', [HistoricoFuncionalController::class, 'show'])->name('servidores.historico');
            Route::post('servidores/{servidor}/historico-funcional/vinculos', [HistoricoFuncionalController::class, 'storeVinculo'])->name('servidores.historico.vinculos.store');
            Route::post('servidores/{servidor}/historico-funcional/designacoes', [HistoricoFuncionalController::class, 'storeDesignacao'])->name('servidores.historico.designacoes.store');
            Route::post('servidores/{servidor}/historico-funcional/vantagens', [HistoricoFuncionalController::class, 'storeVantagem'])->name('servidores.historico.vantagens.store');
            Route::patch('servidores/{servidor}/historico-funcional/designacoes/{designacao}/encerrar', [HistoricoFuncionalController::class, 'encerrarDesignacao'])->name('servidores.historico.designacoes.encerrar');
            Route::patch('servidores/{servidor}/historico-funcional/vantagens/{vantagem}/encerrar', [HistoricoFuncionalController::class, 'encerrarVantagem'])->name('servidores.historico.vantagens.encerrar');

            // ===== COMPETÊNCIAS =====
            Route::prefix('competencias')->name('competencias.')->group(function () {
                Route::get('/', [CompetenciaController::class, 'index'])->name('index');
                Route::post('/', [CompetenciaController::class, 'store'])->name('store');
                Route::get('/{competencia}/cobertura', [CompetenciaController::class, 'cobertura'])
                    ->whereNumber('competencia')->name('cobertura');
                Route::post('/{competencia}/fechar', [CompetenciaController::class, 'fechar'])->name('fechar');
                Route::post('/{competencia}/reabrir', [CompetenciaController::class, 'reabrir'])->name('reabrir');
            });

            // ===== RELATÓRIOS =====
            Route::prefix('relatorios')->name('relatorios.')->group(function () {
                Route::get('/resumo', [RelatorioController::class, 'resumo'])->name('resumo');
                Route::get('/folha-espelho', [RelatorioController::class, 'folhaEspelho'])->name('folha-espelho');
                Route::get('/exportar-csv', [RelatorioController::class, 'exportarCsv'])->name('exportar-csv');
            });

            // ===== CONFIGURAÇÕES (somente ADMIN) =====
            Route::middleware(['role:ADMIN'])->group(function () {
                Route::prefix('configuracoes')->name('configuracoes.')->group(function () {
                    Route::get('/', [ConfiguracaoController::class, 'index'])->name('index');
                    Route::put('/', [ConfiguracaoController::class, 'update'])->name('update');
                });
            });
        });
    });

    // ===== AUDITORIA (CENTRAL, ADMIN e AUDITOR) =====
    Route::middleware(['role:CENTRAL|ADMIN|AUDITOR'])->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});
