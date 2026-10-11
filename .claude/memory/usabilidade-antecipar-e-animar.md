---
name: usabilidade-antecipar-e-animar
description: O usuário quer que eu antecipe problemas de usabilidade, padronize recursos entre telas e use animações nas interações
metadata:
  type: feedback
---

Antecipar e padronizar recursos de usabilidade sem esperar o usuário apontar; telas devem ter animações em
transições, inclusões/remoções, inputs e selects ("isto encanta os usuários").

**Why:** em 2026-10-10 o usuário abriu a edição de um contrato com o catálogo vazio, viu só "Digite parte do nome…"
sem saber que precisava cadastrar o item antes, e pediu: consulta (modal ou autosugestão), valor negociado editável na
própria tela, incluir vários itens e animações. Antes disso já tinha apontado o formulário PIX fora da linha clicada e
botões de excluir iguais aos de editar — o padrão é ele achar o atrito que eu deveria ter previsto.

**How to apply:** ao desenhar ou revisar uma tela, testar o caminho de quem chega sem dados (lista vazia, catálogo
vazio) e oferecer o atalho (cadastrar ali mesmo); reaproveitar o mesmo componente/padrão em todas as telas parecidas;
animar com CSS/`<Transition>` do Vue (sem biblioteca nova) respeitando `prefers-reduced-motion`. Ver
[[documentacao-por-tarefa]] e a skill `recibos` (acertos de usabilidade).
