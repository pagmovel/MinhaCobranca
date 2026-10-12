<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Carimbo from '../../Components/Carimbo.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import AvisarWhatsapp from '../../Components/AvisarWhatsapp.vue';
import BaixaEmLote from '../../Components/BaixaEmLote.vue';
import BotaoEnviar from '../../Components/BotaoEnviar.vue';
import BaixaInline from '../../Components/BaixaInline.vue';
import CancelarEmLote from '../../Components/CancelarEmLote.vue';
import CancelarInline from '../../Components/CancelarInline.vue';
import CorrecaoPagamentoInline from '../../Components/CorrecaoPagamentoInline.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import PagamentoInline from '../../Components/PagamentoInline.vue';
import Paginador from '../../Components/Paginador.vue';
import ProrrogarInline from '../../Components/ProrrogarInline.vue';
import { acoesDaCobranca, rotuloDaAcao } from '../../acoesCobranca.js';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { enviarRecibo } from '../../enviarRecibo.js';
import { ajustarAte, anoCompleto } from '../../filtroPeriodo.js';
import { formatarCentavos } from '../../itens.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    padroes: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    contratoFiltro: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    modelosMensagem: { type: Array, required: true },
    prestadorNome: { type: String, required: true },
});

const filtro = reactive({ ...props.filtros });
// Vencendo hoje e vencidas valem para qualquer mês (o servidor ignora o mês nesses dois).
const todosOsMeses = computed(() => ['vence_hoje', 'vencida'].includes(filtro.status));
const vazio = computed(() => ({
    vence_hoje: 'Nenhuma cobrança vence hoje.',
    vencida: 'Nenhuma cobrança vencida em aberto.',
}[props.filtros.status] ?? 'Nenhuma cobrança com vencimento neste período e filtros.'));

// O "Até" se ajusta sozinho (filtroPeriodo.js): o servidor nunca recusa o período por um campo que a pessoa não mexeu.
// Ano ainda pela metade (0002, 0020…) não filtra nem mexe no "Até".
let mesAnterior = filtro.mes;
function escolheuMes() {
    if (!anoCompleto(filtro.mes)) {
        return;
    }
    filtro.ate = ajustarAte(mesAnterior, filtro.mes, filtro.ate);
    mesAnterior = filtro.mes;
    escolheuPeriodo();
}
// Mexeu no próprio "Até": vazio vira o mês; fora do limite, o erro do servidor aparece embaixo dele (é o campo certo).
function escolheuAte() {
    if (!anoCompleto(filtro.ate)) {
        return;
    }
    filtro.ate ||= filtro.mes;
    escolheuPeriodo();
}

// A lista vem de /cobrancas/dados em blocos de páginas (usarListaPaginada.js: URL, foco e recarga depois das ações,
// que chegam pelo evento "success" do Inertia); a tela só fecha a faixa aberta.
const {
    linhas: linhasDaPagina, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina,
} = usarListaPaginada({
    url: '/cobrancas/dados', filtro, componente: 'Cobrancas/Index', endereco: '/cobrancas',
    aoMostrar: () => { faixa.value = null; limparSelecao(); },
});
const erroAte = computed(() => erros.value.ate ?? '');
// Cliente escolhido (ou apagado) aplica na hora, como os selects.
watch(() => filtro.cliente_id, filtrar);
const filtrando = computed(() => filtrosAlterados(filtro, props.padroes));
// O mesmo filtro, sem o contrato: só o cliente (usado no link "Ver todos os contratos do cliente").
const semContrato = computed(() => `/cobrancas?${new URLSearchParams({
    mes: filtro.mes, ate: filtro.ate, status: filtro.status, cliente_id: filtro.cliente_id, canceladas: filtro.canceladas ? '1' : '',
})}`);

// O campo de mês muda a cada dígito e a cada parte: o pedido sai 400 ms depois da última mudança.
const filtrarPeriodo = adiar(() => filtrar());
function escolheuPeriodo() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrarPeriodo();
}

const linhas = computed(() => linhasDaPagina.value.map((cobranca) => ({ ...cobranca, acoes: acoesDaCobranca(cobranca) })));
const temPrevistas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'prevista')));
const ROTULO_DO_TOTAL = { vencida: 'Vencidas', vence_hoje: 'Vencendo hoje', a_vencer: 'A vencer', prevista: 'Previstas', paga: 'Pagas', cancelada: 'Canceladas' };
const temCanceladas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'cancelada')));
const naFaixa = ['baixa', 'pagamento', 'cancelar', 'prorrogar', 'corrigir', 'desfazer'];
const perigosa = (acao) => ['cancelar', 'desfazer'].includes(acao);
const avisando = ref(null);

// T17.19: seleção de várias cobranças + ação em lote (prévia no modal, antes de confirmar).
const PENDENTE = ['vencida', 'vence_hoje', 'a_vencer'];
const selecionadas = ref(new Set());
const elegiveisDaPagina = computed(() => linhas.value.filter((c) => c.situacao !== 'prevista'));
const todasSelecionadasDaPagina = computed(() => elegiveisDaPagina.value.length > 0
    && elegiveisDaPagina.value.every((c) => selecionadas.value.has(c.id)));
function alternarTodasDaPagina() {
    const novo = new Set(selecionadas.value);
    if (todasSelecionadasDaPagina.value) {
        elegiveisDaPagina.value.forEach((c) => novo.delete(c.id));
    } else {
        elegiveisDaPagina.value.forEach((c) => novo.add(c.id));
    }
    selecionadas.value = novo;
}
function alternarSelecao(id) {
    const novo = new Set(selecionadas.value);
    if (novo.has(id)) {
        novo.delete(id);
    } else {
        novo.add(id);
    }
    selecionadas.value = novo;
}
function limparSelecao() {
    selecionadas.value = new Set();
}
const cobrancasSelecionadas = computed(() => linhas.value.filter((c) => selecionadas.value.has(c.id)));
const loteAberto = ref(null); // 'baixa' | 'cancelar' | null
const enviandoLote = ref(null); // { feito, total } ou null

async function enviarLote() {
    const elegiveis = cobrancasSelecionadas.value.filter((c) => c.recibo && c.situacao !== 'cancelada');
    enviandoLote.value = { feito: 0, total: elegiveis.length };
    for (const cobranca of elegiveis) {
        try {
            await enviarRecibo({ cobrancaId: cobranca.id, pdfUrl: cobranca.recibo.pdf_url, nomeArquivo: cobranca.recibo.pdf_nome });
        } catch {
            // Segue pras próximas; a lista mostra quais ficaram sem "Enviado em" no fim.
        }
        enviandoLote.value = { feito: enviandoLote.value.feito + 1, total: elegiveis.length };
    }
    limparSelecao();
    enviandoLote.value = null;
}

// Formulário da ação numa faixa logo abaixo da linha (uma por vez); o foco vai para o primeiro campo e volta ao fechar.
const faixa = ref(null);
async function abrirFaixa(cobranca, acao) {
    faixa.value = { id: cobranca.id, acao };
    await nextTick();
    document.getElementById(`faixa-${cobranca.id}`)?.querySelector('input, select, textarea, button')?.focus();
}
async function fecharFaixa() {
    const id = faixa.value?.id;
    faixa.value = null;
    await nextTick();
    document.getElementById(`acoes-${id}`)?.querySelector('a, button')?.focus();
}
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Cobranças</h1>
            <Link href="/cobrancas/create" class="botao">Nova cobrança avulsa</Link>
        </div>

        <p v-if="contratoFiltro" class="mb-4 text-sm text-grafite/80">
            Mostrando só o contrato de R$ {{ contratoFiltro.valor }} (desde {{ contratoFiltro.data_inicio }}) —
            <Link :href="semContrato" class="underline">ver todos os contratos do cliente</Link>.
        </p>

        <!-- Datas com largura fixa (o mês cabe em 11rem); situação e cliente dividem o resto; a última coluna é do tamanho
             da caixa + botão, que ficam na mesma linha (pedido do usuário, 2026-10-11). -->
        <form class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-[11rem_11rem_minmax(0,1fr)_minmax(0,1.4fr)_auto]" @submit.prevent="filtrar">
            <Campo id="mes" :rotulo="todosOsMeses ? 'Mês do vencimento (buscando em todos os meses)' : 'Mês do vencimento'">
                <input id="mes" v-model="filtro.mes" type="month" class="entrada" @change="escolheuMes">
            </Campo>
            <Campo id="ate" rotulo="Até" :erro="erroAte || $page.props.errors?.ate">
                <input id="ate" v-model="filtro.ate" type="month" :min="filtro.mes" class="entrada" @change="escolheuAte">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option value="vence_hoje">Vencendo hoje</option>
                    <option value="vencida">Vencidas</option>
                    <option value="a_vencer">A vencer</option>
                    <option value="pendente">Pendentes (todas em aberto)</option>
                    <option value="paga">Pagas</option>
                    <option value="cancelada">Canceladas</option>
                </select>
            </Campo>
            <Campo id="cliente_id" rotulo="Cliente">
                <BuscaAutosugestao id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />
            </Campo>
            <!-- flex-wrap: sem ele, caixa + botão passam da 5ª coluna em telas de ~1260 px e a página rola na horizontal. -->
            <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
                <!-- Só faz sentido em "Todas": as outras situações já dizem se entram canceladas. -->
                <Transition name="surgir">
                    <label v-if="!filtro.status" class="flex shrink-0 items-center gap-2 py-2 text-sm">
                        <input v-model="filtro.canceladas" type="checkbox" class="size-4 accent-carbono" @change="filtrar">
                        Incluir canceladas
                    </label>
                </Transition>
                <button class="botao-secundario px-4">Filtrar</button>
                <Link v-if="filtrando" href="/cobrancas" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <Transition name="surgir">
            <p v-if="temPrevistas" class="mb-3 font-estreita text-xs text-grafite/75">
                Previstas: calculadas pelos contratos; viram cobrança com recibo perto do vencimento.
            </p>
        </Transition>

        <Transition name="surgir">
            <div v-if="selecionadas.size" class="mb-3 flex flex-wrap items-center gap-3 border border-carbono bg-papel px-4 py-2 text-sm">
                <span class="font-semibold">{{ selecionadas.size }} selecionada(s)</span>
                <template v-if="enviandoLote">
                    <span class="text-grafite/80">Enviando {{ enviandoLote.feito }} de {{ enviandoLote.total }}…</span>
                </template>
                <template v-else>
                    <button type="button" class="botao-secundario" @click="loteAberto = 'baixa'">Dar baixa</button>
                    <button type="button" class="botao-secundario" @click="loteAberto = 'cancelar'">Cancelar</button>
                    <button type="button" class="botao-secundario" @click="enviarLote">Enviar</button>
                    <button type="button" class="font-estreita text-xs text-carbono underline" @click="limparSelecao">Limpar seleção</button>
                </template>
            </div>
        </Transition>

        <BaixaEmLote
            v-if="loteAberto === 'baixa'"
            :cobrancas="cobrancasSelecionadas"
            @fechar="loteAberto = null"
            @sucesso="loteAberto = null; limparSelecao()"
        />
        <CancelarEmLote
            v-if="loteAberto === 'cancelar'"
            :cobrancas="cobrancasSelecionadas"
            @fechar="loteAberto = null"
            @sucesso="loteAberto = null; limparSelecao()"
        />

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando cobranças…"
            :vazio="vazio"
            erro-filtro="Corrija o período acima para ver as cobranças."
            @tentar="recarregar"
        >
            <Paginador
                id="paginador-topo"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
            <!-- Tabela a partir de 1280 px; abaixo disso, cada cobrança vira um cartão (mesmo HTML, outro display). -->
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">
                                <input
                                    type="checkbox"
                                    class="size-4 accent-carbono"
                                    :checked="todasSelecionadasDaPagina"
                                    aria-label="Selecionar todas as cobranças desta página"
                                    @change="alternarTodasDaPagina"
                                >
                            </th>
                            <th scope="col" class="px-4 py-2 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2 font-medium">Período</th>
                            <th scope="col" class="px-3 py-2 font-medium">Vencimento</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Valor</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-3 py-2 font-medium">Recibo</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="cobranca in linhas" :key="cobranca.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="xl:px-4 xl:py-3">
                                <input
                                    v-if="cobranca.situacao !== 'prevista'"
                                    type="checkbox"
                                    class="size-4 accent-carbono"
                                    :checked="selecionadas.has(cobranca.id)"
                                    :aria-label="`Selecionar cobrança de ${cobranca.cliente}`"
                                    @change="alternarSelecao(cobranca.id)"
                                >
                            </td>
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ cobranca.cliente }}</p>
                                <p v-if="cobranca.cliente_oficial" class="text-xs text-grafite/80">{{ cobranca.cliente_oficial }}</p>
                                <ul v-if="cobranca.historico?.length" class="mt-1 font-estreita text-xs text-grafite/70">
                                    <li v-for="(linha, indice) in cobranca.historico" :key="indice">{{ linha }}</li>
                                </ul>
                            </td>
                            <td class="order-3 font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                <span class="xl:hidden">Período </span>{{ cobranca.periodo }}
                            </td>
                            <td class="order-3 font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm xl:text-grafite">
                                <span class="xl:hidden">Vence </span>{{ cobranca.vencimento }}
                            </td>
                            <td class="order-1 text-lg font-semibold whitespace-nowrap tabular-nums xl:px-3 xl:py-3 xl:text-right xl:text-base">
                                R$ {{ cobranca.valor }}
                            </td>
                            <td class="order-2 whitespace-nowrap xl:px-3 xl:py-3">
                                <Carimbo :situacao="cobranca.situacao" />
                                <p v-if="cobranca.pago_em" class="mt-1 font-estreita text-xs text-grafite/80">em {{ cobranca.pago_em }}</p>
                                <p v-if="cobranca.avisado_em" class="mt-1 font-estreita text-xs text-grafite/80">Avisado em {{ cobranca.avisado_em }}</p>
                            </td>
                            <td class="order-3 font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                <template v-if="cobranca.recibo"><span class="xl:hidden">Recibo </span>{{ cobranca.recibo.numero }}</template>
                            </td>
                            <td :id="`acoes-${cobranca.id}`" class="order-4 basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <a v-if="cobranca.acoes.principal === 'pdf'" :href="cobranca.recibo.pdf_url" target="_blank" rel="noopener" :class="['botao-secundario', botaoPrincipal]">Ver PDF</a>
                                    <BotaoEnviar
                                        v-else-if="cobranca.acoes.principal === 'enviar'"
                                        :classe-botao="`botao ${botaoPrincipal}`"
                                        :rotulo="rotuloDaAcao('enviar', cobranca)"
                                        :cobranca-id="cobranca.id"
                                        :pdf-url="cobranca.recibo.pdf_url"
                                        :nome-arquivo="cobranca.recibo.pdf_nome"
                                    />
                                    <button v-else-if="cobranca.acoes.principal" type="button" :class="['botao', botaoPrincipal]" @click="abrirFaixa(cobranca, cobranca.acoes.principal)">
                                        {{ rotuloDaAcao(cobranca.acoes.principal, cobranca) }}
                                    </button>
                                    <MenuAcoes v-if="cobranca.acoes.menu.length" v-slot="{ fechar }" :rotulo="`Mais ações: ${cobranca.cliente}`">
                                        <template v-for="acao in cobranca.acoes.menu" :key="acao">
                                            <a v-if="acao === 'pdf'" :href="cobranca.recibo.pdf_url" target="_blank" rel="noopener" :class="itemMenu" @click="fechar">Ver PDF</a>
                                            <Link v-else-if="acao === 'editar'" :href="`/cobrancas/${cobranca.id}/edit`" :class="itemMenu">Editar</Link>
                                            <!-- O menu fica aberto enquanto prepara o PDF, para o erro aparecer; ao marcar "enviada" a página recarrega. -->
                                            <BotaoEnviar
                                                v-else-if="acao === 'enviar'"
                                                class="flex"
                                                :classe-botao="itemMenu"
                                                :rotulo="rotuloDaAcao(acao, cobranca)"
                                                :cobranca-id="cobranca.id"
                                                :pdf-url="cobranca.recibo.pdf_url"
                                                :nome-arquivo="cobranca.recibo.pdf_nome"
                                            />
                                            <button v-else-if="acao === 'avisar'" type="button" :class="itemMenu" @click="fechar(); avisando = cobranca">
                                                Avisar no WhatsApp
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                :class="[itemMenu, { 'text-tinta-vermelha': perigosa(acao) }]"
                                                @click="fechar(); abrirFaixa(cobranca, acao)"
                                            >
                                                {{ rotuloDaAcao(acao, cobranca) }}
                                            </button>
                                        </template>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                        <Transition name="surgir">
                            <tr v-if="faixa?.id === cobranca.id && naFaixa.includes(faixa.acao)" class="block xl:table-row">
                                <td :id="`faixa-${cobranca.id}`" colspan="8" class="block border-t border-carbono/10 bg-papel px-4 py-3 xl:table-cell">
                                    <BaixaInline v-if="faixa.acao === 'baixa'" :cobranca-id="cobranca.id" na-faixa @fechar="fecharFaixa" />
                                    <PagamentoInline
                                        v-else-if="faixa.acao === 'pagamento'"
                                        :cobranca-id="cobranca.id"
                                        :pagamento="cobranca.pagamento"
                                        :chaves-pix="chavesPix"
                                        :formas-pagamento="formasPagamento"
                                        na-faixa
                                        @fechar="fecharFaixa"
                                    />
                                    <CancelarInline v-else-if="faixa.acao === 'cancelar'" :cobranca-id="cobranca.id" na-faixa @fechar="fecharFaixa" />
                                    <ProrrogarInline
                                        v-else-if="faixa.acao === 'prorrogar'"
                                        :cobranca-id="cobranca.id"
                                        :vencimento-atual="cobranca.vencimento"
                                        na-faixa
                                        @fechar="fecharFaixa"
                                    />
                                    <CorrecaoPagamentoInline
                                        v-else
                                        :cobranca-id="cobranca.id"
                                        :enviada="cobranca.enviado"
                                        :modo-inicial="faixa.acao"
                                        @fechar="fecharFaixa"
                                    />
                                </td>
                            </tr>
                        </Transition>
                    </tbody>
                    <tfoot key="rodape" class="block border-t-2 border-carbono/20 font-estreita xl:table-footer-group">
                        <tr v-for="total in resumo.totais" :key="total.situacao" class="flex justify-between px-4 py-1.5 xl:table-row xl:p-0">
                            <td colspan="3" class="xl:px-4 xl:py-1.5">{{ ROTULO_DO_TOTAL[total.situacao] }} <span class="text-grafite/60">({{ total.quantidade }})</span></td>
                            <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-1.5">R$ {{ formatarCentavos(total.soma_centavos) }}</td>
                            <td colspan="3" class="hidden xl:table-cell" />
                        </tr>
                        <tr class="flex justify-between border-t border-linha px-4 py-2 font-semibold xl:table-row xl:p-0">
                            <td colspan="3" class="xl:px-4 xl:py-2">{{ temCanceladas ? 'Total (sem canceladas)' : 'Total' }} <span class="font-normal text-grafite/60">({{ resumo.total_geral.quantidade }})</span></td>
                            <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-2">R$ {{ formatarCentavos(resumo.total_geral.soma_centavos) }}</td>
                            <td colspan="3" class="hidden xl:table-cell" />
                        </tr>
                    </tfoot>
                </TransitionGroup>
            </div>
            <Paginador
                id="paginador-base"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
        <AvisarWhatsapp :aberto="Boolean(avisando)" :ids="avisando ? [avisando.id] : []" :modelos="modelosMensagem" :prestador="prestadorNome" @fechar="avisando = null" />
    </CamadaApp>
</template>
