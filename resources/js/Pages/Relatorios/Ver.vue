<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import { formatarCentavos } from '../../itens.js';

const props = defineProps({
    tipo: { type: String, required: true },
    tabela: { type: Object, required: true },
    filtros: { type: Object, required: true },
});

const filtro = reactive({ ...props.filtros });
const comPeriodo = computed(() => props.tipo !== 'a-receber');
const csv = computed(() => `/relatorios/${props.tipo}?${new URLSearchParams({ ...props.filtros, formato: 'csv' })}`);
const maximo = computed(() => Math.max(1, ...props.tabela.linhas.flatMap((linha) => [linha.previsto ?? 0, linha.recebido ?? 0])));

const atualizar = () => router.get(`/relatorios/${props.tipo}`, filtro, { preserveState: true, replace: true });
const largura = (centavos) => `${Math.round((centavos / maximo.value) * 100)}%`;
const alinhamento = (coluna) => (coluna.tipo === 'texto' ? 'text-left' : 'text-right tabular-nums');

function valor(coluna, conteudo) {
    if (conteudo === null || conteudo === undefined) {
        return '';
    }

    return coluna.tipo === 'dinheiro' ? `R$ ${formatarCentavos(conteudo)}` : conteudo;
}
</script>

<template>
    <CamadaApp>
        <Link href="/relatorios" class="font-estreita text-sm text-carbono underline">Voltar para relatórios</Link>
        <h1 class="mt-2 text-2xl font-semibold">{{ tabela.titulo }}</h1>
        <p class="mt-1 mb-6 max-w-3xl text-sm text-grafite/80">{{ tabela.descricao }}</p>

        <div class="mb-6 flex flex-wrap items-end gap-4">
            <form v-if="comPeriodo" class="flex flex-wrap items-end gap-4" @submit.prevent="atualizar">
                <Campo id="de" rotulo="De" :erro="$page.props.errors?.de">
                    <input id="de" v-model="filtro.de" type="month" class="entrada" required>
                </Campo>
                <Campo id="ate" rotulo="Até" :erro="$page.props.errors?.ate">
                    <input id="ate" v-model="filtro.ate" type="month" class="entrada" required>
                </Campo>
                <button class="botao-secundario">Atualizar</button>
            </form>
            <a :href="csv" class="botao-secundario">Baixar CSV</a>
        </div>

        <figure v-if="tipo === 'faturamento' && tabela.linhas.length" class="mb-6 border border-linha bg-white p-4">
            <figcaption class="mb-3 flex flex-wrap gap-4 font-estreita text-xs text-grafite/80">
                <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-linha" /> Previsto</span>
                <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-tinta-verde" /> Recebido</span>
            </figcaption>
            <div aria-hidden="true" class="grid gap-2">
                <div v-for="linha in tabela.linhas" :key="linha.mes" class="grid grid-cols-[4.5rem_1fr] items-center gap-3">
                    <span class="font-estreita text-xs tabular-nums">{{ linha.mes }}</span>
                    <span class="grid gap-1">
                        <span class="block h-2 bg-linha" :style="{ width: largura(linha.previsto) }" />
                        <span class="block h-2 bg-tinta-verde" :style="{ width: largura(linha.recebido) }" />
                    </span>
                </div>
            </div>
        </figure>

        <p v-if="!tabela.linhas.length" class="border border-linha bg-white px-4 py-3 text-sm">Nada para mostrar neste período.</p>

        <div v-else class="overflow-x-auto border border-linha bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">{{ tabela.titulo }}</caption>
                <thead class="font-estreita text-xs text-grafite/75">
                    <tr>
                        <th v-for="coluna in tabela.colunas" :key="coluna.chave" scope="col" class="px-3 py-2 font-medium" :class="alinhamento(coluna)">
                            {{ coluna.rotulo }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linha">
                    <tr v-for="(linha, indice) in tabela.linhas" :key="indice">
                        <td v-for="coluna in tabela.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                            {{ valor(coluna, linha[coluna.chave]) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-linha font-semibold">
                    <tr>
                        <td v-for="coluna in tabela.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                            {{ valor(coluna, tabela.totais[coluna.chave]) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </CamadaApp>
</template>
