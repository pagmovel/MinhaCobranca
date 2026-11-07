<script setup>
import { reactive, watch } from 'vue';
import EstadoDaLista from './EstadoDaLista.vue';
import LinhaCobranca from './LinhaCobranca.vue';
import Paginador from './Paginador.vue';
import { formatarCentavos } from '../itens.js';
import { paginadorVisivel } from '../paginacao.js';
import { usarListaPaginada } from '../usarListaPaginada.js';

/**
 * Uma lista do painel, carregada por conta própria (/painel/dados?lista=…): 10 por página, paginador só embaixo e só
 * quando o total passa da menor opção (seis paginadores numa tela de resumo pesariam demais); contagem e soma no título.
 * Emite `resumo` (`{ lista, quantidade, soma_centavos }`, o total do resultado inteiro e não só da página) a cada carga,
 * para quem monta várias listas ao mesmo tempo (abas do Almanaque) mostrar a contagem de cada uma.
 */
const props = defineProps({
    lista: { type: String, required: true },
    titulo: { type: String, required: true },
    dica: { type: String, default: '' }, // uma frase sob o título explicando a etapa
    vazio: { type: String, required: true },
});
const emit = defineEmits(['resumo']);

const OPCOES = [10, 25, 50];
const filtro = reactive({ lista: props.lista, pagina: 1, por_pagina: 10 });
const { linhas, resumo, carregando, falha, estado, irPara, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/painel/dados', filtro, componente: 'Painel', idDoFoco: `lista-${props.lista}`,
});

watch(resumo, (carga) => {
    if (carga) {
        emit('resumo', { lista: props.lista, quantidade: carga.total_geral.quantidade, soma_centavos: carga.total_geral.soma_centavos });
    }
}, { immediate: true });
</script>

<template>
    <section class="mt-8" :aria-labelledby="`titulo-${lista}`">
        <h2 :id="`titulo-${lista}`" class="mb-2 text-lg font-semibold">
            {{ titulo }}
            <span v-if="resumo" class="font-estreita font-normal text-grafite/70">
                ({{ resumo.total_geral.quantidade }})<template v-if="resumo.total_geral.quantidade"> · R$ {{ formatarCentavos(resumo.total_geral.soma_centavos) }}</template>
            </span>
        </h2>
        <p v-if="dica" class="-mt-1 mb-2 text-sm text-grafite/70">{{ dica }}</p>
        <EstadoDaLista :estado="estado" :falha="falha" :carregando="`Carregando ${titulo.toLowerCase()}…`" :vazio="vazio" :linhas="3" @tentar="recarregar">
            <ul
                :id="`lista-${lista}`"
                tabindex="-1"
                class="divide-y divide-linha border border-linha bg-superficie transition-opacity focus:outline-none"
                :class="{ 'opacity-60': carregando }"
                :aria-busy="carregando"
            >
                <LinhaCobranca v-for="cobranca in linhas" :key="cobranca.id" :cobranca="cobranca">
                    <slot :cobranca="cobranca" />
                </LinhaCobranca>
            </ul>
            <Paginador
                v-if="paginadorVisivel(resumo.pagina.total, OPCOES)"
                :id="`paginador-${lista}`"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                :opcoes="OPCOES"
                :rotulo="`Páginas: ${titulo}`"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
    </section>
</template>
