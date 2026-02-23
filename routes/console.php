<?php

use Illuminate\Support\Facades\Schedule;

// Desativar delegações vencidas diariamente à meia-noite
Schedule::command('delegacoes:desativar-vencidas')->daily();

// Verificar SLA de lançamentos pendentes diariamente às 8h
Schedule::command('sla:verificar')->dailyAt('08:00');

// C5: Notificar prazos próximos do vencimento diariamente às 7h
Schedule::call(function () {
    \App\Services\NotificacaoService::notificarPrazosProximos(3);
})->dailyAt('07:00')->name('notificar-prazos-proximos');

// Fechar competências e prazos setoriais diariamente à meia-noite
Schedule::command('frequencia:fechar-competencias')->daily();
