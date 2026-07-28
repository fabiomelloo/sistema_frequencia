# Matriz de regras dos itens mensais

Esta matriz é a referência funcional para substituir as colunas das planilhas por regras do sistema. Uma sigla somente pode ser marcada como **validada** no catálogo quando sua definição administrativa estiver confirmada e registrada nas instruções do item.

## Separação obrigatória

- **Cadastro funcional:** informação com vigência, que não deve ser redigitada mensalmente.
- **Informação mensal do setor:** fato ou quantidade que muda em cada competência.
- **Ocorrência:** fato associado a um período ou a dias específicos.
- **Cálculo do sistema:** resultado produzido por uma regra previamente validada.

## Inventário extraído dos modelos recebidos

| Sigla/campo | Interpretação observada | Origem esperada | Forma provável | Situação da regra |
|---|---|---|---|---|
| CARGO | Cargo do servidor | Cadastro funcional | Texto | Conceito conhecido; falta catálogo oficial de cargos |
| SIT | Vínculo ou designação, com valores como EFET, CTT, DAS, FGT e FCT | Cadastro funcional com vigência | Nível/referência | Pendente: a coluna mistura vínculo e função |
| MATR | Matrícula funcional | Cadastro funcional | Texto | Conhecida |
| NOME | Nome do servidor | Cadastro funcional | Texto | Conhecida |
| CHs | Carga horária semanal | Cadastro funcional com vigência | Horas | Pendente: confirmar alterações dentro da competência |
| GRT/GRI | Gratificação ou responsabilidade técnica | Cadastro funcional ou setor mensal | Marcador, nível ou valor | Pendente de definição oficial |
| INS | Insalubridade | Cadastro funcional ou setor mensal | Percentual | Percentuais observados: 10%, 20% e 40%; vigência e documentação pendentes |
| PER | Periculosidade | Cadastro funcional ou setor mensal | Percentual | Percentual observado: 30%; vigência e documentação pendentes |
| ADN | Adicional noturno | Setor mensal | Dias ou horas | Pendente: confirmar unidade e regra de cálculo |
| FREQ | Frequência do período | Setor mensal e ocorrências | Marcador | INT e FLTs já possuem suporte inicial |
| DIAS | Dias vinculados à frequência ou ocorrência | Ocorrência | Dias | Suporte estruturado existente; faltam regras de sobreposição e escala |
| OBSERVAÇÃO | Férias, licenças, atestados, viagens, correções e outros fatos | Ocorrência ou setor mensal | Texto estruturado | Tipos principais já modelados |
| HC | Informação específica observada na Educação | A confirmar | Horas, nível ou marcador | Pendente de definição oficial |
| VGED | Vantagem específica observada na Educação | A confirmar | Dias, nível ou valor | Pendente de definição oficial |

## Evidência necessária para validar uma regra

Cada item deve informar no catálogo:

1. código oficial utilizado pela folha de pagamento;
2. sigla apresentada aos usuários;
3. grupo do evento;
4. origem da informação;
5. forma de lançamento;
6. se gera efeito financeiro;
7. campos e documentos obrigatórios;
8. limites e valores permitidos;
9. setores ou servidores autorizados;
10. instrução administrativa que fundamenta o lançamento.

## Regras já aplicadas pelo sistema

- itens percentuais devem exigir percentual;
- itens em dias devem exigir quantidade de dias;
- itens monetários devem exigir valor e respectivos limites;
- uma regra não pode ser marcada como validada sem instrução oficial;
- a validação registra usuário, data e hora;
- eventos anteriores permanecem disponíveis, mas ficam com revisão pendente até confirmação administrativa;
- aprovação ou alteração do catálogo gera auditoria pelo fluxo existente.
- vantagens de origem funcional são transportadas automaticamente para a frequência quando sua vigência cruza o período;
- itens informados pelo setor exigem regra homologada e autorização explícita para a lotação;
- o setor não pode alterar ou remover a fotografia de uma vantagem funcional;
- o mesmo item mensal não pode ser repetido para o mesmo servidor na mesma competência;
- as quantidades e valores respeitam unidade e limites definidos no catálogo.

### Coexistência de ocorrências

- o mesmo tipo de ocorrência não pode ocupar duas vezes uma mesma data do servidor;
- somente um estado principal pode ocupar cada data, inclusive quando os registros pertencem a competências diferentes;
- `INCONSISTENCIA_REGISTRO` é um marcador informativo e pode coexistir com outros tipos;
- `PONTO_FACULTATIVO` e `HORARIO_REDUZIDO` podem coexistir com `VIAGEM`, `ATIVIDADE_EXTERNA` ou `CURSO_CAPACITACAO`;
- as demais combinações sobrepostas são bloqueadas;
- períodos e dias específicos são formas alternativas de lançamento e não podem ser informados simultaneamente.

## Pendências administrativas

- separar definitivamente vínculo de designação em `SIT`;
- confirmar níveis permitidos de DAS, FGT e FCT;
- confirmar se GRT/GRI representa marcador, nível, percentual ou valor;
- definir a unidade oficial de ADN;
- definir HC e VGED para a Secretaria de Educação;
- confirmar documentos exigidos para INS, PER, gratificações e designações;
- obter o layout oficial de integração com a folha de pagamento.
