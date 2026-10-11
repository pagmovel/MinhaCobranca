# Fase 17 — Facilidades de uso (módulo Cobrança e base comum)

**Pedido do usuário (2026-10-10):** "Isto tem que ser o foco principal. Não é legal quando estamos numa tela de
lançamentos e precisamos sair perdendo tudo que já fizemos porque faltou cadastrar outra coisa em outra tela." Também:
consulta ao catálogo (modal ou autosugestão), valor negociado visível e editável trazendo o preço do catálogo, incluir
vários itens, "Vencendo hoje" no filtro de cobranças e animações em transições, inclusões, inputs e selects.
Direção aprovada pelo usuário na mesma conversa.

Branch: `feat/modulo-cobranca-catalogo` (continua). Sem mudança no banco (nenhuma migração).

## Regras de design (valem para telas novas e antigas)
1. **Quem está lançando nunca sai da tela para cadastrar o que falta.** Cliente, produto/serviço e chave PIX ganham
   cadastro rápido em modal no próprio formulário; o registro novo já volta escolhido. O formulário de fundo não perde
   nada.
2. **Lista aparece ao focar.** Campos de autosugestão mostram os primeiros registros sem precisar digitar; digitar
   filtra.
3. **Valor negociado.** Preço do catálogo é sugestão: ao incluir o item, o foco vai para o preço da linha.
4. **Filtros aplicam ao escolher** (select/mês), sem clique extra.
5. **Animações** só com CSS e `<Transition>` do Vue (sem biblioteca nova), curtas (150–250 ms), e desligadas com
   `prefers-reduced-motion`.
6. Mesmo componente em todas as telas parecidas (contrato e cobrança usam os mesmos).

## Tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T17.1 | Filtro de cobranças: Vencendo hoje, Vencidas (ignoram o mês), A vencer; aplica ao escolher | testada (suíte 255/255; navegador ok) — commit `4e7fb2d` |
| T17.2 | Base: `enviarJson()` (CSRF, 422 → erros), `Modal.vue` acessível (foco preso, Esc, volta o foco), animações base em `app.css` | testada (JS 16/16; build ok) |
| T17.3 | Itens: catálogo ao focar, "Ver catálogo" (modal com busca), "Cadastrar «texto»" (modal → `POST /produtos-servicos` JSON), foco no preço, "Incluir outro" | testada (suíte 264/264; navegador: lista ao focar, indicador girando, catálogo em modal, foco no preço, erro 422 no modal) |
| T17.4 | Cliente sem sair da tela: "Cadastrar cliente «texto»" no contrato e na cobrança (modal com CNPJ/CEP → `POST /clientes` JSON) | testada (suíte; navegador: modal abre com o texto digitado, Esc fecha sem perder o formulário) — gravação real não exercitada no smoke (sem autorização para criar dados) |
| T17.5 | Chave PIX sem sair da tela: "Nova chave PIX" na forma de pagamento (modal → `POST /perfil/chaves-pix` JSON) | testada (suíte; navegador: modal na forma de pagamento, erro de chave inválida, 390 px) |
| T17.6 | Animações padronizadas: linhas de itens e chaves PIX, listas de sugestão, avisos, troca de página, total | testada (navegador: 6 telas animadas sem erro no console; filtro preserva estado; build ok) |
| T17.7 | Smoke no navegador (1280 e 390 px), revisão, merge e push | testada (revisão opus: 0 Critical, 2 Important + 1 Minor de segurança corrigidos; suíte 264/264; JS 16/16; navegador ok) — merge na main |
| T17.8 | Descrição opcional; recibo lista os itens (`Nome: q unidade × R$ p = R$ t`) e a descrição complementar abaixo; limite de 25 linhas somando os dois (pedido do usuário, aprovado 2026-10-10) | testada (suíte 271/271; navegador ok) |

Estados: `pendente` → `concluída` (código pronto) → `testada` (suíte/smoke) → `validada` (usuário confirmou).

## Contratos entre tarefas
- `enviarJson(url, dados, metodo = 'POST'): Promise<{dados}|{erros}|{mensagem}>` (nunca lança) — em `resources/js/http.js`.
- Endpoints existentes respondem JSON quando o pedido aceita JSON (`$request->expectsJson()`): 201 com o registro no
  formato da autosugestão; validação continua no mesmo FormRequest (422 automático).
- `BuscaAutosugestao.vue` ganha: busca com termo vazio ao focar (servidor devolve os primeiros 10 quando `q` vazio,
  só para `GET` com `todos=1`), slot/emit `cadastrar(texto)` mostrado quando não há resultado ou sempre no fim.

## Revisão final (2026-10-10, subagente opus)
- Corrigidos: (I1) lista da autosugestão reabria depois do blur — `saiu()` cancela a busca e a resposta só abre a
  lista se o campo ainda tem foco; (I2) Esc/Tab perdidos no catálogo depois de "Incluir" — listener do Modal no
  `document` + foco vai para o próximo "Incluir"; (M8→Important) trava do banco recusa `DB_URL` preenchida
  (`TravaDoBancoDeTesteTest` RED→GREEN); (M3) `enviarJson` trata 200 sem JSON como falha (`http.test.js` RED→GREEN);
  (M1) 1 letra mostra só "Cadastrar"; (M2) clique não duplica a busca; (M4) chave PIX nova recarrega `chavesPix`;
  (M5) `temClientes` removido; (M6) linha que sai de `ul` fica `absolute`; (M7) teste com cabeçalhos reais do Inertia.
- Não julgados (decisão): durações das animações (regra 5), `color-mix()` (navegadores suportados não definidos),
  vários `Modal` por linha (só renderizam abertos), heurística CPF/CNPJ do cadastro rápido de cliente.
