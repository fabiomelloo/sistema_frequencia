# Homologacao do MVP funcional

Este roteiro transforma o MVP funcional forte em uma homologacao controlada. A meta e provar que os setores conseguem substituir a planilha pelo lancamento nativo do sistema, com conferencia, auditoria, evidencias e exportacao, antes de qualquer uso com dados reais de producao.

## Premissas

- usar somente MySQL ou MariaDB compativel;
- nao importar planilhas como fluxo operacional;
- usar dados anonimizados ou uma base autorizada para teste;
- testar uma competencia completa no periodo institucional de 11 a 10;
- registrar responsavel, data, ambiente e resultado de cada etapa;
- interromper a homologacao se houver falha de auditoria, perda de anexo, erro de permissao ou divergencia no fechamento.

## Participantes

| Papel | Responsabilidade |
| --- | --- |
| TI | ambiente, deploy, banco, backup, logs e prontidao tecnica |
| RH/Folha | catalogo oficial de itens, layout de exportacao e regra de efeitos financeiros |
| Central | conferencia, devolucoes, aprovacao e fechamento |
| Setor piloto | lancamento mensal, anexos e correcao de devolucoes |
| Auditoria/controle | consulta de trilha, evidencias e segregacao de funcoes |

## Preparacao do ambiente

Antes de iniciar o teste funcional, execute:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan sistema:verificar-prontidao
php artisan auditoria:verificar-integridade
php artisan route:list
```

O ambiente esta apto para homologacao somente quando:

- `sistema:verificar-prontidao` informar que o sistema esta pronto;
- `auditoria:verificar-integridade` nao apontar divergencia;
- nao existir rota operacional de importacao de planilha;
- o armazenamento privado aceitar escrita e leitura;
- o banco usado for MySQL/MariaDB, sem fallback SQLite.

## Massa minima de teste

Crie uma competencia e cadastros que cubram os casos abaixo:

- dois setores ativos, sendo um setor piloto e um setor sem obrigacao quando nao houver servidor elegivel;
- servidores efetivos, contratados e com designacao funcional;
- servidor transferido ou desligado dentro do periodo;
- servidor com frequencia integral;
- servidor com falta em dias especificos;
- servidor com ocorrencia por intervalo;
- item mensal homologado e autorizado para a lotacao;
- evidencia obrigatoria aceita;
- evidencia recusada e reenviada;
- devolucao da Central e novo envio pelo setor.

## Fluxo de aceite funcional

1. Abrir a competencia com data inicial e final corretas.
2. Conferir se a cobertura identifica os setores obrigatorios.
3. Entrar como usuario setorial e abrir a frequencia do setor.
4. Validar se a fotografia dos servidores preserva vinculo, lotacao, cargo e carga horaria do periodo.
5. Lancar frequencia integral para um servidor.
6. Lancar ocorrencia com faltas para outro servidor.
7. Lancar item mensal permitido pelo catalogo.
8. Anexar evidencia quando a regra exigir documento.
9. Tentar enviar com pendencia proposital e confirmar o bloqueio.
10. Corrigir a pendencia e enviar a frequencia para a Central.
11. Conferir individualmente os servidores pela Central.
12. Devolver uma frequencia com apontamento objetivo.
13. Corrigir pelo setor e reenviar.
14. Aprovar a frequencia pela Central.
15. Fechar a competencia somente apos todas as obrigacoes aprovadas.
16. Gerar exportacao institucional e baixar o arquivo privado.
17. Validar hash, metadados e conteudo do arquivo com RH/Folha.
18. Consultar auditoria dos acessos, alteracoes, downloads e decisoes.

## Criterios de aprovacao

O MVP pode ser aceito para piloto institucional quando todos os itens abaixo forem verdadeiros:

- nenhum lancamento mensal dependeu de planilha;
- cada setor visualizou apenas seus proprios dados operacionais;
- a Central conseguiu aprovar, devolver e reavaliar sem editar diretamente o lancamento do setor;
- anexos ficaram fora de `public` e so foram acessados por rota autorizada;
- o envio bloqueou edicoes indevidas;
- divergencias impediram aprovacao enquanto nao resolvidas;
- fechamento da competencia foi bloqueado ate a cobertura ficar completa;
- exportacao bateu com o layout homologado pelo RH/Folha;
- auditoria encadeada continuou integra apos todo o teste;
- backup e restauracao foram ensaiados em ambiente isolado antes de dados reais.

## Pendencias que bloqueiam go-live

- catalogo oficial de itens sem validacao formal;
- layout de exportacao ainda nao aprovado pelo setor de folha;
- perfis ou responsaveis por setor sem ato/designacao interna;
- ambiente sem HTTPS, dominio institucional ou cofre de segredos;
- backup sem copia externa e sem teste de restauracao;
- ausencia de monitoramento, alertas e rotina de resposta a incidente;
- politica documental, retencao e antimalware nao definidas.

## Evidencia da homologacao

Ao final, registre em ata ou processo administrativo:

- versao ou commit testado;
- banco e ambiente usados;
- competencia testada;
- setores participantes;
- usuarios responsaveis;
- resultado de cada etapa do fluxo de aceite;
- arquivos de exportacao gerados e seus hashes;
- resultado da verificacao de auditoria;
- pendencias aceitas ou bloqueadoras;
- decisao final: reprovado, aprovado com ressalvas ou aprovado para piloto.

