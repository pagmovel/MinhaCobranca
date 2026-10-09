---
name: regras-banco-recibos
description: Neste projeto, toda escrita no banco real passa pelo PORTÃO-BANCO do plano; testes só no SQLite em memória.
metadata:
  type: project
---

Toda escrita no banco da aplicação — desde 2026-10-09 o PostgreSQL de desenvolvimento
`207.180.205.211:1`, banco `recibos`, usuário `recibos_app` (antes: `database/database.sqlite`) (migrate, app:criar-admin, smoke test) segue o
PORTÃO-BANCO do plano `.claude/plans/2026-10-09-sistema-recibos.md`.
**Why:** regra global do usuário; autorização não é transitiva.
**How to apply:** db:show → db:show --counts → --pretend/comando literal → esperar "sim".
