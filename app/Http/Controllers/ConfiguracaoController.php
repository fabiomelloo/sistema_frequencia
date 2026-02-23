<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ConfiguracaoController extends Controller
{
    public function index(): View
    {
        $configuracoes = Configuracao::orderBy('chave')->get();
        return view('admin.configuracoes.index', compact('configuracoes'));
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->except(['_token', '_method']);
        $atualizadas = 0;

        foreach ($dados as $chave => $valor) {
            // Ignoramos campos vazios a menos que explicitamente permitido (aqui vamos permitir resetar se for intencional)
            // Se vier nulo, pode ser um field vazio. Vamos checar se existe a chave no dict de config default pra não criar lixo
            if (is_array($valor)) {
                $valor = json_encode($valor); // Just in case
            }
            
            $config = Configuracao::where('chave', $chave)->first();
            
            if ($config) {
                if ($config->valor !== $valor) {
                    $dadosAntes = $config->toArray();
                    $config->valor = $valor;
                    $config->save();
                    
                    AuditService::registrar('EDITOU', 'Configuracao', $config->id,
                        "Configuração '{$chave}' alterada de '{$dadosAntes['valor']}' para '{$valor}'"
                    );
                    $atualizadas++;
                }
            } else {
                // Nova configuração (opcional permitir criar pela view, mas geralmente vêm pré-definidas via seeder)
                // Vamos assumir que as chaves enviadas pelo formulário já existem ou são válidas.
                $novaConfig = Configuracao::create([
                    'chave' => $chave,
                    'valor' => $valor,
                ]);
                AuditService::registrar('CRIOU', 'Configuracao', $novaConfig->id,
                    "Configuração '{$chave}' criada com valor '{$valor}'"
                );
                $atualizadas++;
            }
        }

        return redirect()
            ->route('admin.configuracoes.index')
            ->with('success', "{$atualizadas} configuração(ões) atualizada(s) com sucesso!");
    }
}
