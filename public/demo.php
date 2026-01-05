<?php
$page = $_GET['page'] ?? 'home';

$setores = [
    ['id' => 1, 'nome' => 'Recursos Humanos', 'sigla' => 'RH'],
    ['id' => 2, 'nome' => 'Financeiro', 'sigla' => 'FIN'],
    ['id' => 3, 'nome' => 'Departamento Pessoal', 'sigla' => 'DP'],
];

$eventos = [
    ['id' => 1, 'codigo' => '1089', 'descricao' => 'Insalubridade'],
    ['id' => 2, 'codigo' => '0050', 'descricao' => 'Adicional Noturno'],
    ['id' => 3, 'codigo' => '0100', 'descricao' => 'Abono Pontualidade'],
];

$lancamentos = [
    ['servidor_matricula' => '001', 'servidor_nome' => 'João Silva', 'evento' => 'Insalubridade', 'setor' => 'RH', 'valor' => 150.00, 'status' => 'PENDENTE', 'data' => '2026-01-05 08:30'],
    ['servidor_matricula' => '002', 'servidor_nome' => 'Maria Santos', 'evento' => 'Adicional Noturno', 'setor' => 'RH', 'valor' => 200.00, 'status' => 'CONFERIDO', 'data' => '2026-01-04 14:20'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Frequência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .badge-pendente { background-color: #ffc107; color: #000; }
        .badge-conferido { background-color: #28a745; }
        .badge-rejeitado { background-color: #dc3545; }
    </style>
</head>
<body>
    <nav class="navbar bg-white border-bottom">
        <div class="container-fluid">
            <span class="navbar-brand">Sistema de Frequência</span>
            <div class="ms-auto">
                <a href="/?page=home" class="btn btn-sm <?php echo $page === 'home' ? 'btn-primary' : 'btn-outline-primary'; ?>">Home</a>
                <a href="/?page=lancamentos" class="btn btn-sm <?php echo $page === 'lancamentos' ? 'btn-primary' : 'btn-outline-primary'; ?>">Lançamentos (SETORIAL)</a>
                <a href="/?page=painel" class="btn btn-sm <?php echo $page === 'painel' ? 'btn-primary' : 'btn-outline-primary'; ?>">Painel (CENTRAL)</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($page === 'home'): ?>
            <h2 class="mb-4">Demo - Sistema de Frequência</h2>

            <!-- Credenciais 
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light"><strong>SETORIAL</strong></div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Email:</strong> setorial.rh@example.com</p>
                            <p class="mb-2"><strong>Senha:</strong> password</p>
                            <small class="text-muted">Criar e editar lançamentos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light"><strong>CENTRAL</strong></div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Email:</strong> central@example.com</p>
                            <p class="mb-2"><strong>Senha:</strong> password</p>
                            <small class="text-muted">Conferência e exportação</small>
                        </div>
                    </div>
                </div>
            </div>  -->

            <!-- Lançamentos -->
            <div class="card mb-4">
                <div class="card-header bg-light"><strong>Lançamentos</strong></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Matrícula</th>
                                <th>Servidor</th>
                                <th>Evento</th>
                                <th>Setor</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $l): ?>
                            <tr>
                                <td><?php echo $l['servidor_matricula']; ?></td>
                                <td><?php echo $l['servidor_nome']; ?></td>
                                <td><?php echo $l['evento']; ?></td>
                                <td><?php echo $l['setor']; ?></td>
                                <td>R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($l['status']); ?>"><?php echo $l['status']; ?></span></td>
                                <td><?php echo $l['data']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Setores -->
            <div class="card mb-4">
                <div class="card-header bg-light"><strong>Setores</strong></div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($setores as $s): ?>
                        <div class="col-md-4 mb-3">
                            <div class="border p-3 rounded">
                                <h6 class="mb-2"><?php echo $s['nome']; ?></h6>
                                <small class="text-muted">Sigla: <strong><?php echo $s['sigla']; ?></strong></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Eventos -->
            <div class="card mb-4">
                <div class="card-header bg-light"><strong>Eventos</strong></div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($eventos as $e): ?>
                        <div class="col-md-4 mb-3">
                            <div class="border p-3 rounded">
                                <h6 class="mb-2"><?php echo $e['descricao']; ?></h6>
                                <small class="text-muted">Código: <strong><?php echo $e['codigo']; ?></strong></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            
        <?php elseif ($page === 'lancamentos'): ?>
            <h2 class="mb-4">Meus Lançamentos (SETORIAL)</h2>
            <p class="text-muted">Usuário: setorial.rh@example.com</p>

            <div class="row mb-4">
                <div class="col-md-12">
                    <a href="/?page=novo" class="btn btn-primary">Novo Lançamento</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light"><strong>Lançamentos Criados</strong></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Matrícula</th>
                                <th>Servidor</th>
                                <th>Evento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $l): ?>
                            <tr>
                                <td><?php echo $l['servidor_matricula']; ?></td>
                                <td><?php echo $l['servidor_nome']; ?></td>
                                <td><?php echo $l['evento']; ?></td>
                                <td>R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($l['status']); ?>"><?php echo $l['status']; ?></span></td>
                                <td>
                                    <?php if ($l['status'] === 'PENDENTE'): ?>
                                        <a href="#" class="btn btn-sm btn-warning">Editar</a>
                                        <a href="#" class="btn btn-sm btn-danger">Deletar</a>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($page === 'novo'): ?>
            <h2 class="mb-4">Novo Lançamento</h2>

            <div class="card" style="max-width: 600px;">
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label"><strong>Servidor *</strong></label>
                            <select class="form-select">
                                <option>-- Selecione --</option>
                                <option>001 - João Silva</option>
                                <option>002 - Maria Santos</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Evento *</strong></label>
                            <select class="form-select">
                                <option>-- Selecione --</option>
                                <option>1089 - Insalubridade</option>
                                <option>0050 - Adicional Noturno</option>
                                <option>0100 - Abono Pontualidade</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Dias</label>
                            <input type="number" class="form-control" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observação</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary">Enviar Lançamento</button>
                            <a href="/?page=lancamentos" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'painel'): ?>
            <h2 class="mb-4">Painel de Conferência (CENTRAL)</h2>
            <p class="text-muted">Usuário: central@example.com</p>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger">PENDENTE (1)</button>
                        <button type="button" class="btn btn-success">CONFERIDO (1)</button>
                        <button type="button" class="btn btn-secondary">EXPORTADO</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light"><strong>Lançamentos Pendentes</strong></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Matrícula</th>
                                <th>Servidor</th>
                                <th>Evento</th>
                                <th>Setor</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-danger">
                                <td>001</td>
                                <td>João Silva</td>
                                <td>Insalubridade</td>
                                <td>RH</td>
                                <td>R$ 150,00</td>
                                <td><span class="badge badge-pendente">PENDENTE</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-success">Aprovar</a>
                                    <a href="#" class="btn btn-sm btn-danger">Rejeitar</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-light"><strong>Lançamentos Conferidos</strong></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Matrícula</th>
                                <th>Servidor</th>
                                <th>Evento</th>
                                <th>Setor</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-success">
                                <td>002</td>
                                <td>Maria Santos</td>
                                <td>Adicional Noturno</td>
                                <td>RH</td>
                                <td>R$ 200,00</td>
                                <td><span class="badge badge-conferido">CONFERIDO</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <button class="btn btn-success mt-3">Exportar TXT</button>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <footer class="bg-light text-center py-3 mt-5 border-top">
        <small class="text-muted">© 2026 Sistema de Frequência</small>
    </footer>
</body>
</html>
