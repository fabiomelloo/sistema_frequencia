<?php

use App\Services\NotificacaoService;
use Illuminate\Support\Facades\Schedule;

// Desativar delegações vencidas diariamente à meia-noite
Schedule::command('delegacoes:desativar-vencidas')
    ->daily()
    ->name('delegacoes-desativar-vencidas')
    ->withoutOverlapping()
    ->onOneServer();

// Verificar SLA de lançamentos pendentes diariamente às 8h
Schedule::command('sla:verificar')
    ->dailyAt('08:00')
    ->name('sla-verificar')
    ->withoutOverlapping()
    ->onOneServer();

// C5: Notificar prazos próximos do vencimento diariamente às 7h
Schedule::call(function () {
    NotificacaoService::notificarPrazosProximos(3);
})->dailyAt('07:00')
    ->name('notificar-prazos-proximos')
    ->withoutOverlapping()
    ->onOneServer();

// Fechar competências e prazos setoriais diariamente à meia-noite
Schedule::command('frequencia:fechar-competencias')
    ->daily()
    ->name('frequencia-fechar-competencias')
    ->withoutOverlapping()
    ->onOneServer();

// Limpar arquivos TXT de exportação de folha com mais de 7 dias
Schedule::command('sistema:limpar-exportacoes --dias=7')
    ->dailyAt('02:00')
    ->name('sistema-limpar-exportacoes')
    ->withoutOverlapping()
    ->onOneServer();

// Verificar diariamente se a trilha de auditoria continua integra
Schedule::command('auditoria:verificar-integridade')
    ->dailyAt('03:00')
    ->name('auditoria-verificar-integridade')
    ->withoutOverlapping()
    ->onOneServer();
