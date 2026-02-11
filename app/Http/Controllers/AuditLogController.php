<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::orderBy('created_at', 'desc');

        if ($request->filled('acao')) {
            $query->where('acao', $request->acao);
        }
        if ($request->filled('modelo')) {
            $query->where('modelo', $request->modelo);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim . ' 23:59:59');
        }

        $logs = $query->paginate(20)->withQueryString();

        $acoes = AuditLog::select('acao')->distinct()->pluck('acao');
        $modelos = AuditLog::select('modelo')->distinct()->pluck('modelo');

        return view('admin.audit.index', [
            'logs' => $logs,
            'acoes' => $acoes,
            'modelos' => $modelos,
            'filtros' => $request->only(['acao', 'modelo', 'user_id', 'data_inicio', 'data_fim']),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit.show', [
            'log' => $auditLog,
        ]);
    }
}
