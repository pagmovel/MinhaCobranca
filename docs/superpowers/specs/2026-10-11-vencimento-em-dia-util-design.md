# Vencimento em dia útil — especificação (Fase 23)

**Pedido do usuário (2026-10-11):** "antes de gerar uma cobrança, verificar se o vencimento cai num final de semana ou
feriado e se sim, alterar o vencimento daquela cobrança para o primeiro dia útil seguinte".
**Desenho aprovado em chat ("sim", 2026-10-11).** Respostas do usuário: feriados nacionais + locais; locais da cidade e do
estado do **prestador** (uma lista no perfil); **Carnaval conta**; avulsa: **avisar e sugerir**; já geradas: **ajustar as
em aberto e não enviadas**.

## 1. Regra
- **Dia útil** = não é sábado, não é domingo e não é feriado (nacional, Carnaval ou da lista local do usuário).
- O vencimento calculado para um período de contrato que cair em dia não útil vai para o **primeiro dia útil seguinte**.
- **O período não muda** (início e fim continuam; o índice único `(contrato_id, periodo_inicio)` não é afetado). Só a data
  de vencimento.
- A geração antecipada (`users.dias_antecedencia`) conta a partir do **vencimento ajustado**.
- Vale em todo lugar que calcula vencimento de contrato: geração, previsão (previstas e relatórios), encerramento
  (períodos emitidos na hora) e a pergunta dos retroativos. O teste-guarda
  `test_previsao_bate_com_o_que_o_gerador_cria` continua valendo.

## 2. Calendário (fontes conferidas em 2026-10-11 no planalto.gov.br)
| Feriado | Data | Fonte |
|---|---|---|
| Confraternização Universal, Tiradentes, Dia do Trabalho, Independência, Finados, Proclamação da República, Natal | 1/1, 21/4, 1/5, 7/9, 2/11, 15/11, 25/12 | Lei 662/1949, art. 1º (redação da Lei 10.607/2002) |
| Nossa Senhora Aparecida | 12/10 | Lei 6.802/1980, art. 1º |
| Dia Nacional de Zumbi e da Consciência Negra | 20/11 | Lei 14.759/2023, art. 1º (publicada em 22/12/2023: vale a partir de 2024) |
| Carnaval (segunda e terça) | Páscoa − 48 e − 47 dias | **Não é feriado por lei** (ponto facultativo); entra por decisão do usuário (bancos fechados) |
| Sexta-feira da Paixão | Páscoa − 2 dias | **Feriado municipal** (Lei 9.093/1995, art. 2º: religiosos "declarados em lei municipal ... neste incluída a Sexta-Feira da Paixão") — ver decisão D1 |
| Corpus Christi | Páscoa + 60 dias | Feriado municipal onde a lei local declarar → opção da lista local |

- Páscoa calculada no código (algoritmo de Meeus/Jones/Butcher, calendário gregoriano). Sem serviço externo.
- `App\Support\DiasUteis` (puro, sem banco): recebe a lista local do usuário; `ehDiaUtil(data)`, `proximoDiaUtil(data)`
  e `motivo(data)` ("sábado", "domingo", "Tiradentes", "Carnaval", nome do feriado local) para os avisos da tela.
  Teste unitário com datas conhecidas (Páscoa de 2024, 2025, 2026, 2027; feriados encadeados, ex.: sexta feriado +
  fim de semana; virada de ano).

## 3. Feriados locais do usuário
- Tabela `feriados` (`user_id`, `descricao`, `tipo`: `anual` | `corpus_christi` | `unico`, `dia`, `mes`, `data`), por
  usuário, isolada pela relação (`findOrFail`, 404 em alheio). Migração: **PORTÃO-BANCO**.
  - `anual`: dia/mês todo ano (ex.: 2 de julho — Independência da Bahia; aniversário da cidade).
  - `corpus_christi`: móvel, calculado pela Páscoa.
  - `unico`: uma data só (feriado decretado num ano).
- Tela **"Feriados"** (no perfil; item do menu "Meu perfil" ou seção da página): lista com incluir/editar/excluir na
  própria linha (padrão 8), os nacionais e o Carnaval mostrados só para leitura com as datas do ano corrente.

## 4. Cobranças já geradas (ajuste com prévia — padrão 9)
- Na tela "Feriados", bloco **"Cobranças em aberto que vencem em dia não útil"**: lista as cobranças **pendentes, não
  enviadas e de contrato** cujo vencimento cai em dia não útil, com a data atual, o motivo e a data nova; botão
  **"Ajustar N cobranças"**. Ajustar = vencimento novo + recibo refeito (mesmo número), numa transação.
- O mesmo bloco faz o **ajuste inicial** depois que a regra entrar (fins de semana já existentes) e reaparece quando a
  pessoa cadastra um feriado local que atinge cobranças em aberto.
- Enviadas, pagas e canceladas **nunca** mudam aqui.

## 5. Cobrança avulsa
- Data digitada é respeitada. Se cair em dia não útil, o formulário mostra aviso com o motivo e o botão
  **"Usar <próximo dia útil>"** (ex.: "Cai num sábado. Próximo dia útil: segunda, 13/10"). O cálculo vem do servidor
  (`GET /dias-uteis/proximo?data=`, só leitura, mesma regra), para não duplicar o calendário no JS.

## 6. Decisões para o usuário revisar nesta especificação
- **D1 — Sexta-feira da Paixão:** por lei é municipal, mas os bancos fecham no país todo (o mesmo motivo do Carnaval).
  Proposta: **automática**, como o Carnaval. Alternativa: opção da lista local (como Corpus Christi).
- **D2 — Avulsas já emitidas no ajuste em lote:** proposta: **ficam fora** (a data foi digitada pela pessoa; o aviso do
  item 5 vale ao editar). Só cobranças de contrato entram na prévia.
- **D3 — Boleto:** cobrança com linha digitável traz o vencimento do banco dentro do código. Mudar a data no recibo não
  muda o boleto (o banco já aceita pagamento no próximo dia útil). Proposta: ajustar mesmo assim e mostrar, na prévia,
  "tem boleto: confira a data no banco" nessas linhas.

## 7. Fora do escopo
- Feriados estaduais/municipais automáticos (não há fonte oficial gratuita única; a lista é do usuário).
- Feriados da cidade do cliente.
- Mudar período, valor ou número do recibo.

## 8. Testes (resumo)
- `DiasUteis`: Páscoa, Carnaval, nacionais (20/11 só de 2024 em diante), sábado/domingo, sequência de não úteis, lista
  local (anual, Corpus Christi, única).
- Calculadora/gerador/previsão: vencimento no sábado vai para segunda; período intacto; antecedência pelo ajustado;
  guarda previsão = gerador.
- Feriados: isolamento por usuário (404), validação (dia/mês válidos; 31/2 recusado).
- Ajuste em lote: só pendentes não enviadas de contrato; mesmo número de recibo; enviadas/pagas/canceladas intactas;
  prévia com contagem certa.
- Avulsa: endpoint do próximo dia útil (só leitura, exige sessão).
