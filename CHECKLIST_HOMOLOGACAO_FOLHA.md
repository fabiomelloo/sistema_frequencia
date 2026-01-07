# Checklist de Homologação para Integração com Folha de Pagamento

## 🎯 OBJETIVO

Garantir que o sistema está pronto para exportar dados confiáveis e consistentes para o sistema de folha de pagamento.

---

## ✅ FASE 1: VALIDAÇÃO DE REGRAS DE NEGÓCIO

### 1.1 Insalubridade e Periculosidade

- [ ] **Teste 1.1.1:** Tentar criar lançamento com `porcentagem_insalubridade = 20` E `porcentagem_periculosidade = 30`
  - **Esperado:** ❌ Bloqueado com mensagem clara
  - **Validação:** Verificar mensagem: "Insalubridade e periculosidade não podem coexistir"

- [ ] **Teste 1.1.2:** Tentar inserir direto no banco via SQL:
  ```sql
  INSERT INTO lancamentos_setoriais (..., porcentagem_insalubridade, porcentagem_periculosidade)
  VALUES (..., 20, 30);
  ```
  - **Esperado:** ❌ Constraint `chk_insalubridade_periculosidade` deve bloquear

- [ ] **Teste 1.1.3:** Criar lançamento apenas com insalubridade
  - **Esperado:** ✅ Permitido

- [ ] **Teste 1.1.4:** Criar lançamento apenas com periculosidade
  - **Esperado:** ✅ Permitido

---

### 1.2 Adicional de Turno

- [ ] **Teste 1.2.1:** Criar evento com `tipo_evento = 'ADICIONAL_TURNO'`
  - **Esperado:** ✅ Criado com sucesso

- [ ] **Teste 1.2.2:** Criar servidor com `funcao_vigia = false`
  - **Esperado:** ✅ Criado com sucesso

- [ ] **Teste 1.2.3:** Tentar lançar adicional de turno para servidor sem `funcao_vigia = true`
  - **Esperado:** ❌ Bloqueado com mensagem: "Adicional de turno só pode ser aplicado para servidores com função de vigia"

- [ ] **Teste 1.2.4:** Atualizar servidor para `funcao_vigia = true`
  - **Esperado:** ✅ Atualizado

- [ ] **Teste 1.2.5:** Lançar adicional de turno para servidor com `funcao_vigia = true`
  - **Esperado:** ✅ Permitido

- [ ] **Teste 1.2.6:** Verificar que regra usa `tipo_evento` e não `codigo_evento`
  - **Validação:** Mudar `codigo_evento` do evento → Regra ainda deve funcionar

---

### 1.3 Adicional Noturno

- [ ] **Teste 1.3.1:** Criar evento com `tipo_evento = 'ADICIONAL_NOTURNO'`
  - **Esperado:** ✅ Criado com sucesso

- [ ] **Teste 1.3.2:** Criar servidor com `trabalha_noturno = false`
  - **Esperado:** ✅ Criado com sucesso

- [ ] **Teste 1.3.3:** Tentar lançar adicional noturno para servidor sem `trabalha_noturno = true`
  - **Esperado:** ❌ Bloqueado com mensagem: "Adicional noturno só pode ser aplicado quando há trabalho noturno real"

- [ ] **Teste 1.3.4:** Atualizar servidor para `trabalha_noturno = true`
  - **Esperado:** ✅ Atualizado

- [ ] **Teste 1.3.5:** Lançar adicional noturno sem `dias_noturnos`
  - **Esperado:** ❌ Bloqueado: "Dias noturnos são obrigatórios para adicional noturno"

- [ ] **Teste 1.3.6:** Lançar adicional noturno com `dias_noturnos = 5` e `dias_lancados = 3`
  - **Esperado:** ❌ Bloqueado: "Dias noturnos não podem ser maiores que dias lançados"

- [ ] **Teste 1.3.7:** Lançar adicional noturno com `dias_noturnos = 3` e `dias_lancados = 5`
  - **Esperado:** ✅ Permitido

---

### 1.4 Dias Trabalhados

- [ ] **Teste 1.4.1:** Tentar lançar `dias_lancados = 32` (mais que dias do mês)
  - **Esperado:** ❌ Bloqueado: "Dias lançados não podem ser maiores que os dias do mês (31)"

- [ ] **Teste 1.4.2:** Lançar `dias_lancados = 20` sem valor nem adicionais
  - **Esperado:** ❌ Bloqueado: "Dias trabalhados devem estar coerentes com os adicionais aplicados"

- [ ] **Teste 1.4.3:** Lançar `dias_lancados = 20` com `valor = 1000`
  - **Esperado:** ✅ Permitido

- [ ] **Teste 1.4.4:** Lançar `dias_lancados = NULL` para evento que não exige dias
  - **Esperado:** ✅ Permitido

- [ ] **Teste 1.4.5:** Lançar `dias_lancados = NULL` para evento que exige dias
  - **Esperado:** ❌ Bloqueado: "Dias lançados é obrigatório para este evento"

---

## ✅ FASE 2: EXPORTAÇÃO

### 2.1 Integridade do Arquivo

- [ ] **Teste 2.1.1:** Exportar lançamentos conferidos
  - **Esperado:** ✅ Arquivo TXT gerado

- [ ] **Teste 2.1.2:** Verificar hash SHA-256 do arquivo
  - **Validação:** Hash deve estar em `exportacoes_folha.hash_arquivo`

- [ ] **Teste 2.1.3:** Re-exportar mesmo período sem alterações
  - **Esperado:** ✅ Hash deve ser idêntico

- [ ] **Teste 2.1.4:** Modificar arquivo manualmente e verificar hash
  - **Esperado:** ✅ Hash deve mudar (detecta alteração)

- [ ] **Teste 2.1.5:** Verificar que arquivo tem exatamente 37 caracteres por linha
  - **Validação:** Contar caracteres de cada linha do arquivo

---

### 2.2 Formato TXT

- [ ] **Teste 2.2.1:** Verificar código do evento (10 posições, zero à esquerda)
  - **Exemplo:** Código "888" → "0000000888"
  - **Validação:** Verificar padding correto

- [ ] **Teste 2.2.2:** Verificar matrícula (13 posições, zero à esquerda)
  - **Exemplo:** Matrícula "12345" → "00000000012345"
  - **Validação:** Verificar padding correto

- [ ] **Teste 2.2.3:** Verificar valor (14 posições, zero à esquerda, 2 casas decimais implícitas)
  - **Exemplo:** Valor 1000.50 → "000000000100050"
  - **Validação:** Verificar formatação correta

- [ ] **Teste 2.2.4:** Tentar exportar com código evento > 10 caracteres
  - **Esperado:** ❌ Erro antes de gerar arquivo: "código do evento excede tamanho máximo"

- [ ] **Teste 2.2.5:** Tentar exportar com matrícula > 13 caracteres
  - **Esperado:** ❌ Erro antes de gerar arquivo: "matrícula excede tamanho máximo"

---

### 2.3 Validação Pré-Exportação

- [ ] **Teste 2.3.1:** Tentar exportar sem lançamentos conferidos
  - **Esperado:** ❌ Mensagem: "Nenhum lançamento conferido com valor para exportação"

- [ ] **Teste 2.3.2:** Criar lançamento com `status = PENDENTE` e tentar exportar
  - **Esperado:** ❌ Não deve aparecer na exportação

- [ ] **Teste 2.3.3:** Criar lançamento com `status = CONFERIDO` mas `valor = NULL`
  - **Esperado:** ❌ Não deve aparecer na exportação

- [ ] **Teste 2.3.4:** Criar lançamento com `status = CONFERIDO` e `valor = 0`
  - **Esperado:** ❌ Não deve aparecer na exportação

- [ ] **Teste 2.3.5:** Criar lançamento com `status = CONFERIDO` e `valor > 0`
  - **Esperado:** ✅ Deve aparecer na exportação

---

### 2.4 Rastreabilidade

- [ ] **Teste 2.4.1:** Exportar e verificar registro em `exportacoes_folha`
  - **Validação:** Verificar todos os campos preenchidos

- [ ] **Teste 2.4.2:** Verificar vínculo em `exportacao_lancamento`
  - **Validação:** Cada lançamento exportado deve ter registro na pivot

- [ ] **Teste 2.4.3:** Verificar log de exportação
  - **Validação:** Log deve conter: exportacao_id, arquivo, quantidade, usuario_id, hash

- [ ] **Teste 2.4.4:** Verificar que `usuario_id` em exportação é o usuário logado
  - **Validação:** Comparar com `auth()->id()`

- [ ] **Teste 2.4.5:** Verificar que `periodo` está no formato YYYYMM
  - **Validação:** Exemplo: "202601" para janeiro de 2026

---

## ✅ FASE 3: REGRAS DE NEGÓCIO

### 3.1 Tipo de Evento

- [ ] **Teste 3.1.1:** Criar evento sem `tipo_evento`
  - **Esperado:** ❌ Bloqueado: "tipo_evento é obrigatório"

- [ ] **Teste 3.1.2:** Criar evento com `tipo_evento = 'INVALIDO'`
  - **Esperado:** ❌ Bloqueado: "tipo_evento deve ser um dos valores válidos"

- [ ] **Teste 3.1.3:** Criar evento com `tipo_evento = 'ADICIONAL_TURNO'`
  - **Esperado:** ✅ Criado com sucesso

- [ ] **Teste 3.1.4:** Verificar que validações usam `tipo_evento` e não `codigo_evento`
  - **Validação:** Mudar `codigo_evento` → Validações ainda funcionam

---

### 3.2 Status

- [ ] **Teste 3.2.1:** Tentar editar lançamento com `status = CONFERIDO`
  - **Esperado:** ❌ Bloqueado: "Não autorizado"

- [ ] **Teste 3.2.2:** Tentar aprovar lançamento com `status = CONFERIDO`
  - **Esperado:** ❌ Bloqueado: "Apenas lançamentos com status PENDENTE podem ser aprovados"

- [ ] **Teste 3.2.3:** Tentar exportar lançamento com `status = PENDENTE`
  - **Esperado:** ❌ Não deve aparecer na exportação

- [ ] **Teste 3.2.4:** Editar lançamento com `status = PENDENTE`
  - **Esperado:** ✅ Permitido

---

### 3.3 Permissões

- [ ] **Teste 3.3.1:** Usuário SETORIAL tentar ver eventos de outro setor
  - **Esperado:** ❌ Não deve aparecer na lista

- [ ] **Teste 3.3.2:** Usuário SETORIAL tentar lançar para servidor de outro setor
  - **Esperado:** ❌ Bloqueado: "Servidor não pertence ao seu setor"

- [ ] **Teste 3.3.3:** Usuário SETORIAL tentar lançar evento sem permissão
  - **Esperado:** ❌ Bloqueado: "Seu setor não possui direito a este evento"

---

## ✅ FASE 4: AUDITORIA

### 4.1 Logs

- [ ] **Teste 4.1.1:** Exportar e verificar log em `storage/logs/laravel.log`
  - **Validação:** Log deve conter: "Exportação de folha realizada" com todos os dados

- [ ] **Teste 4.1.2:** Tentar exportar com erro e verificar log
  - **Validação:** Log deve conter: "Erro ao exportar lançamentos" com trace completo

---

### 4.2 Rastreabilidade

- [ ] **Teste 4.2.1:** Aprovar lançamento e verificar `id_validador`
  - **Esperado:** ✅ Deve conter ID do usuário que aprovou

- [ ] **Teste 4.2.2:** Aprovar lançamento e verificar `validated_at`
  - **Esperado:** ✅ Deve conter timestamp da aprovação

- [ ] **Teste 4.2.3:** Exportar e verificar `exportado_em` nos lançamentos
  - **Esperado:** ✅ Deve conter timestamp da exportação

- [ ] **Teste 4.2.4:** Exportar e verificar `usuario_id` em `exportacoes_folha`
  - **Esperado:** ✅ Deve conter ID do usuário que exportou

---

## ✅ FASE 5: PERFORMANCE

### 5.1 Queries

- [ ] **Teste 5.1.1:** Verificar que não há N+1 queries ao listar lançamentos
  - **Validação:** Usar `DB::enableQueryLog()` e contar queries

- [ ] **Teste 5.1.2:** Verificar uso de eager loading (`with()`)
  - **Validação:** Verificar que relacionamentos são carregados de uma vez

- [ ] **Teste 5.1.3:** Verificar índices no banco
  - **Validação:** `\d lancamentos_setoriais` e verificar índices em `status`, `servidor_id`, etc.

---

### 5.2 Cache

- [ ] **Teste 5.2.1:** Verificar cache de evento no Form Request
  - **Validação:** `getEvento()` deve fazer apenas 1 query mesmo com múltiplas validações

- [ ] **Teste 5.2.2:** Verificar que não há queries repetidas
  - **Validação:** Contar queries totais em uma requisição de criação

---

## 📊 RESULTADO ESPERADO

### ✅ **APROVADO PARA PRODUÇÃO**

- Todas as validações funcionando
- Constraints do banco ativas
- Exportação gerando arquivos corretos
- Auditoria completa
- Performance adequada

### ❌ **NÃO APROVADO**

- Se qualquer teste falhar, corrigir antes de produção
- Documentar problemas encontrados
- Re-testar após correções

---

## 📝 OBSERVAÇÕES

- Execute os testes em ambiente de homologação
- Documente qualquer comportamento inesperado
- Mantenha evidências (screenshots, logs) dos testes
- Valide com equipe de folha antes de produção

---

**Última atualização:** 2026-01-07  
**Versão do sistema:** 1.0.0
