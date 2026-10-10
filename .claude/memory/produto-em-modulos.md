---
name: produto-em-modulos
description: O sistema é um produto SaaS organizado em módulos (Clientes, Cobrança, Fornecedores...) que serão vendidos por planos de assinatura; documentação deve nomear os módulos.
metadata:
  type: project
---

Decisão do usuário em 2026-10-10: o "Gerenciador de Cobranças" vai ser comercializado por **planos de
assinatura**, e o trabalho é descrito em **módulos**: **Cobrança** (clientes, contratos, cobranças, produtos e
serviços, dashboards, relatórios — em construção) e **Contas a Pagar** (fornecedores, clientes, produtos e serviços
vinculados a fornecedores, dashboards, relatórios — a desenhar); depois Notas fiscais e Integração com emissores.
Clientes e produtos/serviços são cadastros compartilhados entre os módulos (tabela na skill `recibos`).
Os módulos entram aos poucos e **cada um deve poder ser comercializado de forma independente**.

**Why:** cada plano de assinatura poderá liberar um conjunto de módulos; a documentação precisa mostrar
essa fronteira desde já.

**How to apply:** specs, planos, skill e README dizem a que módulo cada parte pertence; código novo fica
coeso por módulo (controllers, models, páginas). Liberação por plano (quem pode usar qual módulo) ainda
não existe — não construir sem pedido (YAGNI). Requisito desde já: **um módulo não pode depender de
outro para funcionar**. Dependências entre módulos são opcionais e degradam com elegância (ex.: rentabilidade
mostra só receita se Fornecedores/Contas a pagar não estiverem contratados). Na spec de cada módulo, listar
"funciona sozinho com…" e "se o módulo X estiver ativo, ganha…".
Ver [[merge-e-push-por-fase]].
