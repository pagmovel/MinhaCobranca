<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
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
import { acoesDaCobranca, rotuloDaAcao } from '../../acoesCobranca.js';

const props = defineProps({
    cobrancas: { type: Array, required: true },
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
const temPrevistas = computed(() => props.cobrancas.some((cobranca) => cobranca.situacao === 'prevista'));

// A regra "boolean" do Laravel não aceita "true"/"false" na URL: vai 1 ou nada.
const filtrar = () => router.get('/cobrancas', { ...filtro, canceladas: filtro.canceladas ? 1 : '' }, { preserveState: true, replace: true });
// Escolher um mês é pedir para ver aquele período: sai de "Vencendo hoje"/"Vencidas", que buscam em todos os meses.
function escolheuPeriodo() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrar();
}
// "Até" acompanha o mês enquanto os dois forem iguais (um mês só) e nunca fica antes dele.
let mesAnterior = filtro.mes;
function escolheuMes() {
    if (filtro.ate === mesAnterior || filtro.ate < filtro.mes) {
        filtro.ate = filtro.mes;
    }
    mesAnterior = filtro.mes;
    escolheuPeriodo();
}

const linhas = computed(() => props.cobrancas.map((cobranca) => ({ ...cobranca, acoes: acoesDaCobranca(cobranca) })));
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
            <Campo id="ate" rotulo="Até" :erro="$page.props.errors?.ate">
                <input id="ate" v-model="filtro.ate" type="month" :min="filtro.mes" class="entrada" @change="escolheuPeriodo">
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

        <p v-if="!cobrancas.length" class="border border-carbono bg-white px-4 py-3 text-sm">
            {{ vazio }}
        </p>

        <!-- Tabela a partir de 1280 px; abaixo disso, cada cobrança vira um cartão (mesmo HTML, outro display). -->
        <div v-else class="border border-linha bg-white">
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
            </TransitionGroup>
        </div>
    </CamadaApp>
</template>
