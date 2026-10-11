<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import Paginador from '../../Components/Paginador.vue';
import { formatarCentavos } from '../../itens.js';
import { relatorio } from '../../relatorios.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    tipo: { type: String, required: true },
    filtros: { type: Object, required: true },
});

const cabecalho = relatorio(props.tipo);
const comPeriodo = props.tipo !== 'a-receber';
// O faturamento vem inteiro numa página: tem no máximo 36 linhas, e o gráfico precisa de todas.
const paginado = props.tipo !== 'faturamento';
const filtro = reactive({ ...props.filtros });
const { linhas, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: `/relatorios/${props.tipo}/dados`, filtro, componente: 'Relatorios/Ver', endereco: `/relatorios/${props.tipo}`,
});

const csv = computed(() => `/relatorios/${props.tipo}?${new URLSearchParams({ de: filtro.de, ate: filtro.ate, formato: 'csv' })}`);
const maximo = computed(() => Math.max(1, ...linhas.value.flatMap((linha) => [linha.previsto ?? 0, linha.recebido ?? 0])));
const largura = (centavos) => `${Math.round((centavos / maximo.value) * 100)}%`;
const alinhamento = (coluna) => (coluna.tipo === 'texto' ? 'text-left' : 'text-right tabular-nums');

function valor(coluna, conteudo) {
    if (conteudo === null || conteudo === undefined) {
        return '';
    }

    // Espaço inseparável: no celular a coluna estreita quebrava "R$" de um lado e o número do outro.
    return coluna.tipo === 'dinheiro' ? `R$\u00a0${formatarCentavos(conteudo)}` : conteudo;
}
</script>

<template>
    <CamadaApp>
        <Link href="/relatorios" class="font-estreita text-sm text-carbono underline">Voltar para relatórios</Link>
        <h1 class="mt-2 text-2xl font-semibold">{{ cabecalho.titulo }}</h1>
        <p class="mt-1 mb-6 max-w-3xl text-sm text-grafite/80">{{ resumo?.descricao ?? cabecalho.descricao }}</p>

        <div class="mb-6 flex flex-wrap items-end gap-4">
            <form v-if="comPeriodo" class="flex flex-wrap items-end gap-4" @submit.prevent="filtrar">
                <Campo id="de" rotulo="De" :erro="erros.de">
                    <input id="de" v-model="filtro.de" type="month" class="entrada" required @change="filtrar">
                </Campo>
                <Campo id="ate" rotulo="Até" :erro="erros.ate">
                    <input id="ate" v-model="filtro.ate" type="month" class="entrada" required :min="filtro.de" @change="filtrar">
                </Campo>
            </form>
            <a :href="csv" class="botao-secundario">Baixar CSV</a>
        </div>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando relatório…"
            vazio="Nada para mostrar neste período."
            erro-filtro="Corrija o período acima para ver o relatório."
            @tentar="recarregar"
        >
            <figure v-if="tipo === 'faturamento'" class="mb-6 border border-linha bg-white p-4">
                <figcaption class="mb-3 flex flex-wrap gap-4 font-estreita text-xs text-grafite/80">
                    <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-linha" /> Previsto</span>
                    <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-tinta-verde" /> Recebido</span>
                </figcaption>
                <div aria-hidden="true" class="grid gap-2">
                    <div v-for="linha in linhas" :key="linha.mes" class="grid grid-cols-[4.5rem_1fr] items-center gap-3">
                        <span class="font-estreita text-xs tabular-nums">{{ linha.mes }}</span>
                        <span class="grid gap-1">
                            <span class="block h-2 bg-linha" :style="{ width: largura(linha.previsto) }" />
                            <span class="block h-2 bg-tinta-verde" :style="{ width: largura(linha.recebido) }" />
                        </span>
                    </div>
                </div>
            </figure>

            <Paginador v-if="paginado" id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="relative overflow-x-auto border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ cabecalho.titulo }}</caption>
                    <thead class="font-estreita text-xs text-grafite/75">
                        <tr>
                            <th v-for="coluna in resumo.colunas" :key="coluna.chave" scope="col" class="px-3 py-2 font-medium" :class="alinhamento(coluna)">
                                {{ coluna.rotulo }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linha">
                        <tr v-for="(linha, indice) in linhas" :key="indice">
                            <td v-for="coluna in resumo.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                                {{ valor(coluna, linha[coluna.chave]) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-linha font-semibold">
                        <tr>
                            <td v-for="coluna in resumo.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                                {{ valor(coluna, resumo.totais[coluna.chave]) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <Paginador v-if="paginado" id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
