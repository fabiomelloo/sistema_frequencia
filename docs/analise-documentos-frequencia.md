# Análise dos documentos de frequência recebidos

## Objetivo

Este documento registra o que foi observado nos nove modelos de planilha recebidos e compara esse conteúdo com o domínio já implementado. Ele não define regras de folha ainda não confirmadas e não autoriza conversão automática de texto livre em lançamento financeiro.

## Estrutura comum observada

Os documentos possuem três blocos principais:

1. identificação do órgão, unidade/setor e período de referência;
2. legenda das siglas usadas pela secretaria;
3. relação de servidores com cargo, situação funcional, matrícula, nome, carga horária, adicionais, frequência, dias e observações.

O período mostrado nos exemplos vai do dia 11 de um mês ao dia 10 do mês seguinte. Portanto, não corresponde necessariamente ao mês civil.

Campos recorrentes:

| Documento | Significado observado |
| --- | --- |
| Órgão | Código e nome da secretaria |
| Unidade/setor | Código e nome da unidade responsável |
| Setor | Em alguns arquivos, nível adicional abaixo da unidade |
| Referência | Intervalo explícito de datas |
| Cargo | Cargo ou descrição funcional |
| SIT | Vínculo ou função, com valores como EFET, CTT, DAS, FGT e FCT |
| MATR | Matrícula; existem linhas sem valor |
| CHs | Carga horária semanal |
| GRT | Gratificação de responsabilidade técnica ou nível de gratificação |
| INS | Percentual de insalubridade |
| PER | Periculosidade |
| ADN | Quantidade de dias de adicional noturno |
| FREQ | `INT` para frequência integral e `FLTs` para faltas |
| DIAS | Dias em que houve falta |
| OBSERVAÇÃO | Ocorrências, justificativas e documentos relacionados |

## Variações entre secretarias

- Saúde utiliza com frequência `INS`, `PER`, `GRT`, `ADN`, `FREQ` e `DIAS`.
- Educação acrescenta `HC` e `VGED`, além de níveis próprios de gratificação.
- Há marcações equivalentes representadas por `X`, `V`, percentual ou número.
- Horas extras aparecem em texto livre e, em alguns casos, fora da grade principal.
- As observações contêm férias, licença, atestado, viagem, atividade externa, compensação, exoneração, término de contrato, afastamento previdenciário, decisões legais e valores.
- Cor de célula e posição visual são usadas como destaque, mas não devem ser tratadas como fonte única de regra.

## Compatibilidade com o modelo atual

O sistema já possui conceitos aproveitáveis: servidor, setor, lotação, competência, evento de folha, lançamento, fluxo de aprovação e auditoria. A evolução deve ampliar esses conceitos, não criar um segundo sistema paralelo.

### Lacunas críticas

1. **`DIAS` não significa dias trabalhados.** A legenda dos documentos define esse campo como os dias em que houve falta. O campo atual `dias_trabalhados` não pode receber esse conteúdo, pois isso inverteria a regra.
2. **A competência não é apenas `YYYY-MM`.** O modelo atual calcula mês civil, mas os documentos usam intervalo de 11 a 10. A competência precisa guardar início e fim efetivos.
3. **Setor possui hierarquia e código externo.** Há órgão, unidade e, em alguns casos, setor subordinado. O cadastro atual é plano.
4. **`SIT` mistura conceitos.** EFET/CTT indicam vínculo; DAS/FGT/FCT representam função ou designação. Esses dados não devem ser gravados em um único enum de vínculo.
5. **`GRT` varia por secretaria.** Pode ser marcação, tipo ou nível. Antes de gravar valor financeiro, é necessário identificar espécie, nível e vigência.
6. **A observação é um conjunto de ocorrências.** Um único texto pode conter várias datas e naturezas distintas. Manter apenas texto livre impede validação, anexos, auditoria e cálculo confiável.
7. **Matrícula não é universalmente preenchida.** O cadastro precisa permitir identificação administrativa consistente antes do lançamento mensal.
8. **Há evidências documentais.** Expressões como “com comprovação”, atestados e atos legais exigem vínculo com arquivo/documento e estado de conferência.

## Evolução incremental recomendada

### Estado implementado

- competência preserva a referência `YYYY-MM` e agora registra `data_inicio` e `data_fim`, adotando 11 a 10 como padrão;
- cálculo de dias úteis, elegibilidade do servidor e lotação histórica passaram a usar o período efetivo;
- setor agora aceita código externo e relação opcional com unidade/setor superior;
- ciclos na hierarquia de setores são rejeitados;
- os schemas MySQL principal e de testes receberam as migrations correspondentes.
- ocorrências de frequência agora são registradas separadamente por servidor, setor e competência;
- cada ocorrência aceita período contínuo ou dias específicos não consecutivos, além de justificação, referência de comprovação e texto original;
- o cadastro é restrito ao setor do usuário, respeita o intervalo efetivo da competência e mantém auditoria de criação, edição e remoção;
- registrar uma ocorrência não cria nem altera lançamento financeiro; o mapeamento para folha permanece uma etapa futura dependente de regras homologadas.
- o fluxo operacional principal agora é nativo: cada setor abre a frequência de uma competência diretamente no sistema;
- a abertura cria uma fotografia dos servidores elegíveis e de seus dados funcionais naquele período, preservando o histórico contra alterações cadastrais futuras;
- cada servidor precisa ser classificado como frequência integral ou com faltas; faltas exigem ocorrência e dias previamente registrados;
- a folha da competência só pode ser finalizada quando todas as linhas estiverem preenchidas e consistentes;
- as rotas de importação foram retiradas do fluxo web: a operação corrente ocorre exclusivamente nas telas do sistema.

### Conferência central implementada

- o setor envia a frequência mensal preenchida diretamente no sistema;
- a Central acompanha os envios por competência, setor e situação;
- a Central pode aprovar ou devolver a frequência com orientação obrigatória;
- uma devolução reabre a edição para o setor somente enquanto a competência estiver aberta;
- um novo envio remove a orientação anterior e volta para a fila da Central;
- aprovação, devolução e alterações ficam registradas na auditoria, e o setor recebe notificação interna;
- não existe importação de planilha nesse fluxo operacional.

### Cobertura e fechamento seguro implementados

- a cobertura considera os setores ativos que possuem servidor elegível no período da competência;
- a Central visualiza quais setores não iniciaram, estão preenchendo, aguardam conferência, foram devolvidos ou já estão aprovados;
- a competência somente pode ser fechada quando todas as frequências mensais obrigatórias estiverem aprovadas;
- setores sem servidor elegível no período não geram uma obrigação artificial de envio;
- o fechamento manual e o fechamento automático utilizam a mesma regra de consistência.

### Fluxo operacional nativo

```text
Cadastro funcional -> abertura da competência -> relação de servidores do setor
-> lançamento de ocorrências e itens mensais -> anexação de evidências
-> conferência setorial -> conferência individual pela Central
-> aprovação da frequência -> exportação institucional autorizada
```

As planilhas recebidas serviram apenas para compreender o domínio e identificar os campos necessários. Elas não são entrada operacional do sistema, não há rota web de importação e os setores devem lançar os dados diretamente nas telas controladas.

## Regras que precisam de confirmação antes da implementação

- qual competência nominal representa o intervalo de 11/06 a 10/07;
- catálogo oficial de `SIT`, `GRT`, `HC`, `VGED`, `INS`, `PER` e tipos de ocorrência;
- se dias e intervalos devem considerar fim de semana, feriado e escala;
- quais ocorrências geram evento financeiro e quais são apenas informativas;
- como identificar servidor quando a matrícula não estiver preenchida;
- quais documentos são obrigatórios para cada justificativa;
- tratamento de horas extras, valores, decisões judiciais e ajustes retroativos.

## Proteção dos dados

Os arquivos contêm dados funcionais e informações potencialmente sensíveis, como afastamentos médicos. O sistema deve aplicar acesso por perfil, auditoria, retenção definida, armazenamento privado de anexos e evitar exposição desses dados em logs.

## Evolução por ondas

A primeira onda do método nativo está documentada em `docs/matriz-regras-itens-mensais.md`. O catálogo de eventos agora diferencia origem, forma de lançamento, efeito financeiro, exigência documental e situação de validação da regra, sem assumir automaticamente o significado das siglas pendentes.

### Onda 2 — Histórico funcional

- o cadastro inicial do servidor grava, na mesma transação, sua identificação, vínculo funcional e lotação inicial;
- cargo, vínculo e carga horária possuem períodos próprios e não são mais alterados diretamente pela edição cadastral;
- os campos legados em `servidores` continuam como projeção do estado vigente para manter compatibilidade até a integração completa das folhas;
- DAS, FGT, FCT, GRT e outras designações são armazenadas separadamente do tipo de vínculo;
- vantagens funcionais somente podem usar itens ativos, homologados e classificados no catálogo como originados no cadastro funcional;
- vigências sobrepostas do mesmo tipo são rejeitadas;
- desligamento encerra vínculo e lotação vigentes; reativação cria novos períodos, sem reabrir ou apagar os anteriores;
- alterações funcionais mantêm usuário responsável, data, documento de referência e auditoria.

### Onda 3 — Itens na frequência mensal

- a relação mensal utiliza o vínculo funcional vigente no fechamento do período e conserva o identificador da origem do snapshot;
- quando mais de um vínculo cruza a competência, o estado do fechamento é apresentado e a mudança funcional é sinalizada para conferência;
- todas as designações e vantagens funcionais que cruzam o intervalo da competência são preservadas com suas vigências, inclusive quando terminam no meio do período;
- vantagens funcionais homologadas entram automaticamente na linha do servidor e não podem ser alteradas pelo setor;
- o setor pode adicionar, editar ou remover somente itens de origem mensal que estejam ativos, homologados e autorizados para sua lotação;
- unidade, limites, observação e referência documental são validados conforme o catálogo;
- itens duplicados para o mesmo servidor, evento e competência são rejeitados;
- envio da frequência bloqueia a edição dos itens, mantendo a mesma regra do restante da folha;
- a Central visualiza os itens e a origem de cada informação na tela de conferência;
- nenhum dos fluxos depende de importação de planilha.

### Onda 4 — Conferência individual e devolução orientada

- cada envio da frequência abre uma nova rodada de conferência, sem apagar as análises das rodadas anteriores;
- a Central registra, por servidor, o resultado `CONFERIDO` ou `DIVERGENTE`, com responsável e data;
- toda divergência exige apontamento objetivo com pelo menos dez caracteres;
- a aprovação da folha fica bloqueada enquanto qualquer servidor da rodada atual estiver pendente ou divergente;
- a devolução ao setor exige ao menos uma divergência individual e uma orientação geral para correção;
- o setor visualiza os apontamentos nas linhas devolvidas, corrige os dados e envia uma nova rodada;
- análises concorrentes com aprovação ou devolução são serializadas por transação e bloqueio da folha no MySQL;
- a situação da conferência é comunicada por texto e cor, com resumo de pendências e erros associados ao servidor correto;
- o processo permanece inteiramente nativo no sistema, sem depender de importação de planilha.

### Onda 5 — Evidências documentais protegidas

- documentos comprobatórios são anexados diretamente à ocorrência ou ao item da competência a que pertencem;
- a exigência documental do catálogo é congelada no snapshot do item mensal, evitando alteração retroativa de folhas antigas;
- itens marcados como obrigatórios e ocorrências que informam possuir comprovação bloqueiam o envio enquanto não houver arquivo anexado;
- são aceitos somente PDF, JPG e PNG de até 10 MB, validados pelo tipo detectado no servidor;
- arquivos recebem nome interno aleatório, hash SHA-256 e ficam no disco privado, fora de `public` e sem URL direta;
- downloads passam por autorização, verificação de integridade e auditoria de leitura;
- o setor acessa somente documentos da própria lotação e não pode alterar anexos depois do envio da frequência;
- a Central aceita ou recusa cada documento; uma recusa exige motivo objetivo e invalida a conferência já registrada para aquele servidor;
- um servidor somente pode ser marcado como conferido quando todos os seus documentos estiverem aceitos;
- exclusões removem o arquivo privado, preservam os metadados por exclusão lógica e geram registro de auditoria sem copiar conteúdo sensível para os logs;
- operações de envio, anexação e remoção são serializadas pela folha no MySQL para evitar mudanças concorrentes durante a conferência;
- antivírus, política definitiva de retenção, backup criptografado e monitoramento do armazenamento pertencem à preparação operacional da Onda 6.

### Onda 6 — Prontidão operacional

- o runtime da aplicação mantém somente o driver `pdo_mysql`; configurações de banco, filas falhas, sessões e cache não possuem fallback SQLite;
- cache compartilhado, locks do scheduler, sessões, lotes e falhas de fila possuem tabelas operacionais no MySQL;
- `/health/ready` verifica conexão, migrations, cache e escrita no armazenamento privado sem expor mensagens internas;
- o comando `sistema:verificar-prontidao --producao` também bloqueia configurações inseguras de URL, debug, fila, sessão, cache, disco privado e backup;
- fila e scheduler executam como processos separados, com tarefas nomeadas, bloqueio de sobreposição e execução única entre instâncias;
- a imagem de produção é imutável, otimizada sem dependências de desenvolvimento e servida por Nginx sem publicar o MySQL;
- backups reúnem dump transacional e armazenamento privado, são criptografados, recebem manifesto SHA-256 e têm procedimento de restauração documentado;
- as rotas web de importação foram removidas: os dados mensais são lançados diretamente no sistema;
- domínio, TLS, monitoramento externo, cofre de segredos, cópia externa de backup e antimalware permanecem como condições externas de go-live.
