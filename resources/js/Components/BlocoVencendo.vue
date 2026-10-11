<script setup>
import { reactive, ref } from 'vue';
import AvisarWhatsapp from './AvisarWhatsapp.vue';
import EstadoDaLista from './EstadoDaLista.vue';
import Paginador from './Paginador.vue';
import { paginadorVisivel } from '../paginacao.js';
import { usarListaPaginada } from '../usarListaPaginada.js';

defineProps({
    diasAviso: { type: Number, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});

const OPCOES = [10, 25, 50];
const filtro = reactive({ lista: 'vencendo', pagina: 1, por_pagina: 10 });
const { linhas, resumo, carregando, falha, estado, irPara, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/painel/dados', filtro, componente: 'Painel', idDoFoco: 'lista-vencendo',
});

const avisando = ref(null);
</script>

<template>
    <section class="mt-8" aria-labelledby="titulo-vencendo">
        <h2 id="titulo-vencendo" class="mb-2 text-lg font-semibold">
            Vencendo nos próximos {{ diasAviso }} dias
            <span v-if="resumo" class="font-estreita font-normal text-grafite/70">({{ resumo.total_geral.quantidade }})</span>
        </h2>
        <EstadoDaLista :estado="estado" :falha="falha" carregando="Carregando…" vazio="Nada vencendo nos próximos dias." :linhas="2" @tentar="recarregar">
            <ul id="lista-vencendo" tabindex="-1" class="divide-y divide-linha border border-linha bg-white transition-opacity focus:outline-none" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <li v-for="grupo in linhas" :key="grupo.cliente_id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ grupo.cliente }}</p>
                        <p class="font-estreita text-grafite/80">
                            {{ grupo.quantidade === 1 ? '1 cobrança' : `${grupo.quantidade} cobranças` }} · {{ grupo.vencimentos.join(', ') }} · R$ {{ grupo.total }}
                            <template v-if="grupo.avisado_em"> · Avisado em {{ grupo.avisado_em }}</template>
                        </p>
                    </div>
                    <button type="button" class="botao-secundario" @click="avisando = grupo">Avisar no WhatsApp</button>
                </li>
            </ul>
            <Paginador
                v-if="paginadorVisivel(resumo.pagina.total, OPCOES)"
                id="paginador-vencendo"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                :opcoes="OPCOES"
                rotulo="Páginas: Vencendo"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
        <AvisarWhatsapp :aberto="Boolean(avisando)" :ids="avisando?.ids ?? []" :modelos="modelos" :prestador="prestador" @fechar="avisando = null" />
    </section>
</template>
