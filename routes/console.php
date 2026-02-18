<?php

use Illuminate\Support\Facades\Schedule;

// Desativar delegações vencidas diariamente à meia-noite
Schedule::command('delegacoes:desativar-vencidas')->daily();

// Verificar SLA de lançamentos pendentes diariamente às 8h
Schedule::command('sla:verificar')->dailyAt('08:00');
