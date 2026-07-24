# Marco do MVP funcional forte

## Escopo comprovado no codebase

- operacao exclusivamente em MySQL, inclusive nos testes automatizados;
- cadastro e historico funcional de servidores e lotacoes;
- competencia no periodo institucional do dia 11 ao dia 10;
- frequencia mensal nativa, ocorrencias estruturadas e itens do catalogo governado;
- conferencia setorial e central, com devolucao motivada e bloqueio apos envio;
- evidencias documentais em armazenamento privado, com revisao e controle por setor;
- exportacao privada com hash, metadados e limpeza por retencao;
- usuarios desativados sem apagar historico, bloqueio temporario e sessoes encerradas;
- auditoria imutavel encadeada por SHA-256 e verificacao diaria;
- prontidao de MySQL, migrations, cache e armazenamento privado;
- nenhuma rota de planilha e servicos legados bloqueados por padrao.

## Evidencias automatizadas

- 54 testes aprovados, com 344 assercoes, no banco MySQL de teste;
- migrations aplicadas ate `2026_07_22_000016`;
- cadeia de auditoria verificada sem divergencias;
- auditoria de dependencias sem avisos de vulnerabilidade;
- cache de configuracao contendo somente a conexao `mysql`.

## Limite do marco

O MVP funcional forte significa que o produto esta pronto para homologacao institucional. Ele nao significa autorizacao automatica para uso de dados reais em producao.

Antes do go-live, continuam obrigatorios:

- validar o catalogo oficial de itens e o layout de exportacao com RH/folha;
- homologar perfis, segregacao de funcoes e responsaveis por setor;
- configurar dominio, HTTPS, segredos e SMTP institucionais;
- implantar monitoramento, alertas e centralizacao de logs;
- manter backup criptografado fora do servidor e executar teste de restauracao;
- definir retencao documental e verificacao antimalware;
- executar teste de aceitacao com uma competencia controlada e dados anonimizados.

O roteiro de aceite funcional esta em `docs/homologacao-mvp.md`.
Os procedimentos tecnicos de deploy, saude, backup, restauracao e rollback estao em `docs/operacao-producao.md`.
