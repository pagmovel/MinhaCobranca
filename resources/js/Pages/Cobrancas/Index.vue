<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Carimbo from '../../Components/Carimbo.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import BotaoEnviar from '../../Components/BotaoEnviar.vue';
import BaixaInline from '../../Components/BaixaInline.vue';
import CancelarInline from '../../Components/CancelarInline.vue';
import CorrecaoPagamentoInline from '../../Components/CorrecaoPagamentoInline.vue';
import PagamentoInline from '../../Components/PagamentoInline.vue';
import Paginador from '../../Components/Paginador.vue';
import { acoesDaCobranca, rotuloDaAcao } from '../../acoesCobranca.js';
import { ajustarAte } from '../../filtroPeriodo.js';
import { buscarJson } from '../../http.js';
import { formatarCentavos } from '../../itens.js';
import { chaveDoFiltro, fatiarBloco } from '../../paginacao.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
// Vencendo hoje e vencidas valem para qualquer mês (o servidor ignora o mês nesses dois).
const todosOsMeses = computed(() => ['vence_hoje', 'vencida'].includes(filtro.status));
const vazio = computed(() => ({
    vence_hoje: 'Nenhuma cobrança vence hoje.',
    vencida: 'Nenhuma cobrança vencida em aberto.',
}[props.filtros.status] ?? 'Nenhuma cobrança com vencimento neste período e filtros.'));

// O "Até" se ajusta sozinho (filtroPeriodo.js): o servidor nunca recusa o período por um campo que a pessoa não mexeu.
let mesAnterior = filtro.mes;
function escolheuMes() {
    filtro.ate = ajustarAte(mesAnterior, filtro.mes, filtro.ate);
    mesAnterior = filtro.mes;
    escolheuPeriodo();
}
// Mexeu no próprio "Até": vazio vira o mês; fora do limite, o erro do servidor aparece embaixo dele (é o campo certo).
function escolheuAte() {
    filtro.ate ||= filtro.mes;
    escolheuPeriodo();
}

// A lista vem de /cobrancas/dados em blocos de páginas; o navegador guarda as páginas por filtro e desenha só a atual.
const linhasDaPagina = ref([]);
const resumo = ref(null); // { totais, total_geral, pagina: { inicio, por_pagina, total } }
const carregando = ref(false);
const carregou = ref(false);
const falha = ref('');
const erroAte = ref('');
let cache = { chave: null, paginas: {} };
let pedido = 0; // só a resposta do pedido mais recente vale (cliques rápidos)

// A regra "boolean" do Laravel não aceita "true"/"false" na URL: canceladas vai 1 ou nada; vazios ficam de fora.
const parametros = (pagina) => new URLSearchParams(
    Object.entries({ ...filtro, canceladas: filtro.canceladas ? 1 : '', pagina })
        .filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined),
).toString();

function mostrar(pagina, focar) {
    filtro.pagina = pagina;
    linhasDaPagina.value = cache.paginas[pagina] ?? [];
    faixa.value = null;
    // Filtros e página na URL (F5, voltar, link), sem pedido ao servidor nem entrada nova no histórico.
    router.replace({ url: `/cobrancas?${parametros(pagina)}`, props: (atuais) => ({ ...atuais, filtros: { ...filtro } }), preserveState: true, preserveScroll: true });
    if (focar) {
        nextTick(() => {
            const topo = document.getElementById('paginador-topo');
            topo?.scrollIntoView({ block: 'start' });
            topo?.focus({ preventScroll: true });
        });
    }
}

async function irPara(pagina, { focar = false } = {}) {
    const chave = chaveDoFiltro(filtro);
    if (cache.chave !== chave) {
        cache = { chave, paginas: {} };
    }
    if (cache.paginas[pagina]) {
        mostrar(pagina, focar);
        return;
    }

    const meu = ++pedido;
    carregando.value = true;
    const { dados, erros, mensagem } = await buscarJson(`/cobrancas/dados?${parametros(pagina)}`);
    if (meu !== pedido) {
        return; // chegou depois de um pedido mais novo: descarta
    }
    carregando.value = false;
    erroAte.value = erros?.ate ?? '';
    falha.value = erros ? '' : (mensagem ?? '');
    if (!dados) {
        return;
    }
    cache.paginas = { ...cache.paginas, ...fatiarBloco(dados.linhas, dados.pagina.inicio, dados.pagina.por_pagina) };
    resumo.value = dados;
    carregou.value = true;
    mostrar(dados.pagina.inicio, focar);
}

// Filtro novo: o cache dele começa vazio na página 1.
const filtrar = () => irPara(1);
const recarregar = () => {
    cache = { chave: null, paginas: {} };
    irPara(filtro.pagina);
};
function escolheuPeriodo() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrar();
}
function escolheuPorPagina(quantidade) {
    filtro.por_pagina = quantidade;
    filtrar();
}

// As ações (baixa, cancelar, enviar…) respondem com o redirect do Inertia, que preserva o estado da página (não
// remonta): o evento "success" só sai de resposta do servidor, então é a deixa para buscar a lista de novo.
let pararDeOuvir = () => {};
onMounted(() => {
    irPara(filtro.pagina);
    pararDeOuvir = router.on('success', (evento) => {
        if (evento.detail.page.component === 'Cobrancas/Index' && !carregando.value) {
            recarregar();
        }
    });
});
onUnmounted(() => pararDeOuvir());

const linhas = computed(() => linhasDaPagina.value.map((cobranca) => ({ ...cobranca, acoes: acoesDaCobranca(cobranca) })));
const temPrevistas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'prevista')));
const ROTULO_DO_TOTAL = { vencida: 'Vencidas', vence_hoje: 'Vencendo hoje', a_vencer: 'A vencer', prevista: 'Previstas', paga: 'Pagas', cancelada: 'Canceladas' };
const temCanceladas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'cancelada')));
const naFaixa = ['baixa', 'pagamento', 'cancelar', 'corrigir', 'desfazer'];
const perigosa = (acao) => ['cancelar', 'desfazer'].includes(acao);
// Mesma largura mínima em todas as linhas: os botões principais ficam alinhados e numa linha só.
const botaoPrincipal = 'min-w-28 whitespace-nowrap';
const itemMenu = 'w-full px-4 py-2 text-left text-sm hover:bg-papel focus-visible:bg-papel focus-visible:outline-none';

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

        <form class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="filtrar">
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
            <div class="flex items-end gap-4">
                <!-- Só faz sentido em "Todas": as outras situações já dizem se entram canceladas. -->
                <Transition name="surgir">
                    <label v-if="!filtro.status" class="flex shrink-0 items-center gap-2 py-2 text-sm">
                        <input v-model="filtro.canceladas" type="checkbox" class="size-4 accent-carbono" @change="filtrar">
                        Incluir canceladas
                    </label>
                </Transition>
                <button class="botao-secundario w-full">Filtrar</button>
            </div>
        </form>

        <Transition name="surgir">
            <p v-if="temPrevistas" class="mb-3 font-estreita text-xs text-grafite/75">
                Previstas: calculadas pelos contratos; viram cobrança com recibo perto do vencimento.
            </p>
        </Transition>

        <p v-if="!carregou && !falha" class="border border-linha bg-white px-4 py-3 text-sm text-grafite/75" role="status">
            Carregando cobranças…
        </p>
        <div v-else-if="falha" class="flex flex-wrap items-center gap-3 border border-tinta-vermelha bg-white px-4 py-3 text-sm">
            <span>{{ falha }}</span>
            <button type="button" class="botao-secundario" @click="recarregar">Tentar de novo</button>
        </div>
        <p v-else-if="!resumo.pagina.total" class="border border-carbono bg-white px-4 py-3 text-sm">
            {{ vazio }}
        </p>

        <template v-else>
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
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ cobranca.cliente }}</p>
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
                                <td :id="`faixa-${cobranca.id}`" colspan="7" class="block border-t border-carbono/10 bg-papel px-4 py-3 xl:table-cell">
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
        </template>
    </CamadaApp>
</template>
