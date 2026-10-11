<script setup>
import { reactive } from 'vue';
import EstadoDaLista from './EstadoDaLista.vue';
import LinhaCobranca from './LinhaCobranca.vue';
import Paginador from './Paginador.vue';
import { formatarCentavos } from '../itens.js';
import { usarListaPaginada } from '../usarListaPaginada.js';

/**
 * Uma lista do painel, carregada por conta própria (/painel/dados?lista=…): 10 por página, paginador só embaixo e só
 * com mais de uma página (seis paginadores numa tela de resumo pesariam demais); contagem e soma no título.
 */
const props = defineProps({
    lista: { type: String, required: true },
    titulo: { type: String, required: true },
    vazio: { type: String, required: true },
});

const filtro = reactive({ lista: props.lista, pagina: 1, por_pagina: 10 });
const { linhas, resumo, carregando, falha, estado, irPara, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/painel/dados', filtro, componente: 'Painel', idDoFoco: `lista-${props.lista}`,
});
</script>

<template>
    <section class="mt-8" :aria-labelledby="`titulo-${lista}`">
        <h2 :id="`titulo-${lista}`" class="mb-2 text-lg font-semibold">
            {{ titulo }}
            <span v-if="resumo" class="font-estreita font-normal text-grafite/70">
                ({{ resumo.total_geral.quantidade }})<template v-if="resumo.total_geral.quantidade"> · R$ {{ formatarCentavos(resumo.total_geral.soma_centavos) }}</template>
            </span>
        </h2>
        <EstadoDaLista :estado="estado" :falha="falha" :carregando="`Carregando ${titulo.toLowerCase()}…`" :vazio="vazio" :linhas="3" @tentar="recarregar">
            <ul
                :id="`lista-${lista}`"
                tabindex="-1"
                class="divide-y divide-linha border border-linha bg-white transition-opacity focus:outline-none"
                :class="{ 'opacity-60': carregando }"
                :aria-busy="carregando"
            >
                <LinhaCobranca v-for="cobranca in linhas" :key="cobranca.id" :cobranca="cobranca">
                    <slot :cobranca="cobranca" />
                </LinhaCobranca>
            </ul>
            <Paginador
                v-if="resumo.pagina.total > filtro.por_pagina"
                :id="`paginador-${lista}`"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                :opcoes="[10, 25, 50]"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
    </section>
</template>
