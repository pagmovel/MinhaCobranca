<script setup>
import { computed, reactive, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { periodoAoRedor } from '../../filtroPeriodo.js';
import { formatarCentavos } from '../../itens.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    produtoFiltro: { type: Object, default: null },
    modalidades: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const PADROES = { cliente_id: '', situacao: '', modalidade: '', forma_pagamento: '', produto_servico_id: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
// A mensagem de vazio segue os filtros do resultado na tela, não o que está sendo digitado (a busca espera 400 ms).
const resultadoFiltrado = computed(() => filtrosAlterados(resumo.value?.filtros ?? {}, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/contratos/dados', filtro, componente: 'Contratos/Index', endereco: '/contratos',
});
// Cliente ou produto escolhido (ou apagado) aplica na hora, como os selects.
watch(() => [filtro.cliente_id, filtro.produto_servico_id], filtrar);

const SITUACAO = {
    ativo: () => ['Ativo', 'verde'],
    encerra: (contrato) => [`Encerra em ${contrato.fim}`, 'laranja'],
    encerrado: () => ['Encerrado', 'apagado'],
};
// Valores de periodicidades diferentes não se somam: uma linha de total por periodicidade.
const PERIODICIDADE = {
    mensal: ['Mensais ativos', 'por mês'],
    trimestral: ['Trimestrais ativos', 'por trimestre'],
    semestral: ['Semestrais ativos', 'por semestre'],
    anual: ['Anuais ativos', 'por ano'],
};
const periodo = periodoAoRedor();
const cobrancasDo = (contrato) => `/cobrancas?${new URLSearchParams({ cliente_id: contrato.cliente_id, ...periodo })}`;
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Contratos</h1>
            <Link href="/contratos/create" class="botao">Novo contrato</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="filtrar">
            <Campo id="cliente_id" rotulo="Cliente" class="sm:col-span-3">
                <BuscaAutosugestao id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />
            </Campo>
            <Campo id="situacao" rotulo="Situação" class="sm:col-span-3">
                <select id="situacao" v-model="filtro.situacao" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="encerrados">Encerrados</option>
                </select>
            </Campo>
            <Campo id="modalidade" rotulo="Modalidade" class="sm:col-span-2">
                <select id="modalidade" v-model="filtro.modalidade" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="modalidade in modalidades" :key="modalidade.valor" :value="modalidade.valor">{{ modalidade.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="forma_pagamento" rotulo="Forma de pagamento" class="sm:col-span-2">
                <select id="forma_pagamento" v-model="filtro.forma_pagamento" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="forma in formasPagamento" :key="forma.valor" :value="forma.valor">{{ forma.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="produto_servico_id" rotulo="Produto ou serviço" class="sm:col-span-2">
                <BuscaAutosugestao
                    id="produto_servico_id"
                    v-model="filtro.produto_servico_id"
                    :inicial="produtoFiltro"
                    url="/produtos-servicos/busca"
                    vazio="Todos os produtos e serviços"
                    nenhum="Nenhum produto ou serviço ativo encontrado."
                />
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/contratos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando contratos…"
            :vazio="resultadoFiltrado ? 'Nenhum contrato com estes filtros.' : 'Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cobrança</th>
                            <th scope="col" class="px-3 py-2 font-medium">Vigência</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Valor</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="contrato in linhas" :key="contrato.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ contrato.cliente }}</p>
                                <p v-if="contrato.resumo" class="text-xs text-grafite/80">{{ contrato.resumo }}</p>
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                {{ contrato.periodicidade }}, {{ contrato.modalidade }}, vence dia {{ contrato.dia_vencimento }}
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                {{ contrato.inicio }}<template v-if="contrato.fim"> a {{ contrato.fim }}</template>
                            </td>
                            <td class="font-semibold whitespace-nowrap tabular-nums xl:px-3 xl:py-3 xl:text-right">R$ {{ contrato.valor }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="SITUACAO[contrato.situacao](contrato)[0]" :tom="SITUACAO[contrato.situacao](contrato)[1]" />
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/contratos/${contrato.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${contrato.cliente}`">
                                        <Link :href="cobrancasDo(contrato)" :class="itemMenu">Ver cobranças do cliente</Link>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="resumo.totais.ativos.length || resumo.totais.encerrados" key="rodape" class="block border-t-2 border-carbono/20 font-estreita xl:table-footer-group">
                        <tr v-for="total in resumo.totais.ativos" :key="total.periodicidade" class="flex justify-between gap-3 px-4 py-1.5 xl:table-row xl:p-0">
                            <td colspan="3" class="xl:px-4 xl:py-1.5">{{ PERIODICIDADE[total.periodicidade][0] }} <span class="text-grafite/60">({{ total.quantidade }})</span></td>
                            <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-1.5">R$ {{ formatarCentavos(total.soma_centavos) }}</td>
                            <td colspan="2" class="text-grafite/60 xl:px-3 xl:py-1.5">{{ PERIODICIDADE[total.periodicidade][1] }}</td>
                        </tr>
                        <tr v-if="resumo.totais.encerrados" class="flex justify-between px-4 py-1.5 text-grafite/70 xl:table-row xl:p-0">
                            <td colspan="6" class="xl:px-4 xl:py-1.5">Encerrados <span class="text-grafite/60">({{ resumo.totais.encerrados }})</span></td>
                        </tr>
                    </tfoot>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
