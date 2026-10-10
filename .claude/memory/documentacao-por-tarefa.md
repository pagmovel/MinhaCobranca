---
name: documentacao-por-tarefa
description: Neste projeto, estado do plano e skill são atualizados a cada tarefa, no commit da própria tarefa.
metadata:
  type: feedback
---

Atualizar a tabela de acompanhamento do plano, o campo Estado da tarefa e a skill
`.claude/skills/recibos/SKILL.md` ao concluir **cada tarefa**, no mesmo commit — não por fase.
**Why:** pedido explícito do usuário em 2026-10-09 durante a execução do plano.
**How to apply:** antes de cada `git commit` de tarefa, editar plano + skill e incluí-los no `git add`.
Relacionado: [[regras-banco-recibos]].
