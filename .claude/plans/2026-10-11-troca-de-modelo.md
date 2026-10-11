# Fase 22 — Trocar o modelo do recibo no cliente vale para as cobranças em aberto

**Pedido do usuário (2026-10-11):** "Ao trocar o modelo do recibo, quero que seja refletido imediatamente nas cobranças
que ainda não foram enviadas e com opção para gerar novamente as que foram enviadas e ainda não pagas."

**Desenho aprovado ("sim", 2026-10-11):**
1. Salvar o cliente com outro modelo refaz, na mesma transação, os recibos das cobranças **pendentes e não enviadas**
   (mesmo número; snapshot com os dados atuais, como já acontece ao editar a cobrança).
2. **Enviadas e não pagas:** opcional, por uma caixa que só aparece quando o modelo muda, com a prévia das contagens.
   Marcada, os recibos são refeitos e as cobranças **voltam para "Para enviar"** (`enviado_em` nulo) para reenviar.
3. **Pagas e canceladas nunca mudam.**
4. Muda a regra de domínio "trocar o modelo vale só para recibos futuros" (skill).

| Tarefa | O quê | Estado |
|---|---|---|
| T22.1 | Servidor: `EmissorDeRecibos::aplicarModeloDoCliente`, `ClienteController::update` (transação), contagens na tela de edição, `regerar_enviadas` no request | testada: `TrocaDeModeloTest` (4) RED → GREEN; PHP 398/398 |
| T22.2 | Tela: aviso com a prévia e a caixa das enviadas no formulário do cliente | concluída (build ok); conferência visual na T22.3 |
| T22.3 | Conferência visual e validação do usuário | pendente |

## Registro de execução
- **T22.1:** `EmissorDeRecibos::aplicarModeloDoCliente(Cliente, bool $incluirEnviadas): int` (pendentes; sem a caixa, só
  `enviado_em` nulo; refaz com `refazer(..., trocouCliente: true)`; enviada refeita volta a `enviado_em` nulo).
  `ClienteController::update` numa transação: compara o modelo antes/depois; mensagem de sucesso diz quantos recibos
  foram refeitos. `formulario()` manda `emAberto {nao_enviadas, enviadas}` para a prévia. `store`/`update` gravam
  `safe()->except('regerar_enviadas')`. O 3º teste (sem troca de modelo nada é refeito) passou antes da implementação: é a
  guarda do comportamento antigo, não prova da mudança.
- **Migração da Fase 21 aplicada no banco de dev** (2026-10-11, "sim" do usuário; `207.180.205.211:1`, `recibos`,
  `recibos_app`, não é produção): `clientes` com 4 linhas antes e depois, todas com apelido vazio; nenhuma pendente.
