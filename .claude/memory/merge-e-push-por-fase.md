---
name: merge-e-push-por-fase
description: Fase concluída pode ser mergeada na main e enviada ao remoto origin sem pedir de novo (autorizado em 2026-10-10).
metadata:
  type: feedback
---

Autorização permanente do usuário (2026-10-10): toda fase concluída (testes e build passando) é
mergeada no `main` com `git merge --no-ff` e enviada com `git push origin main`. Remoto:
`git@github.com:pagmovel/MinhaCobranca.git`. Cada plano novo trabalha num branch `feat/...` saído do `main`.

**Why:** o usuário criou o repositório remoto e pediu que as fases concluídas fossem integradas e publicadas.

**How to apply:** ao fechar uma fase (todas as tarefas `testada`), merge no `main` + push, sem nova
pergunta. Não vale para force push, reescrita de histórico nem fase com teste/build falhando — esses
continuam exigindo confirmação. O branch principal local foi renomeado de `master` para `main`.
Ver também [[documentacao-por-tarefa]].
