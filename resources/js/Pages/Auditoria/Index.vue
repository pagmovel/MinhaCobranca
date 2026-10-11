<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import Paginador from '../../Components/Paginador.vue';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    padroes: { type: Object, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, props.padroes));
const { linhas, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/auditoria/dados', filtro, componente: 'Auditoria/Index', endereco: '/auditoria',
});
</script>

<template>
    <CamadaApp>
        <h1 class="mb-2 text-2xl font-semibold">Auditoria</h1>
        <p class="mb-6 max-w-3xl text-sm text-grafite/80">
            Correções e desfazimentos de baixa, cancelamentos, edições de cobrança e encerramentos ou correções de contrato:
            quem fez, quando e por quê. Os registros não podem ser alterados.
        </p>

        <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="filtrar">
            <Campo id="de" rotulo="De" :erro="erros.de">
                <input id="de" v-model="filtro.de" type="date" class="entrada" @change="filtrar">
            </Campo>
            <Campo id="ate" rotulo="Até" :erro="erros.ate">
                <input id="ate" v-model="filtro.ate" type="date" class="entrada" :min="filtro.de" @change="filtrar">
            </Campo>
            <Link v-if="filtrando" href="/auditoria" class="pb-2 font-estreita text-sm text-carbono underline">Limpar filtros</Link>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando registros…"
            vazio="Nenhuma ação registrada neste período."
            erro-filtro="Corrija o período acima para ver os registros."
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Quando</th>
                            <th scope="col" class="px-3 py-2 font-medium">Registro</th>
                            <th scope="col" class="px-4 py-2 font-medium">Motivo</th>
                        </tr>
                    </thead>
                    <tbody v-for="registro in linhas" :key="registro.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-col gap-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/75 xl:w-px xl:px-4 xl:py-3 xl:text-sm">{{ registro.quando }}</td>
                            <td class="xl:px-3 xl:py-3">
                                <Link v-if="registro.link" :href="registro.link" class="underline">{{ registro.resumo }}</Link>
                                <template v-else>{{ registro.resumo }}</template>
                            </td>
                            <td class="text-grafite/80 xl:px-4 xl:py-3">
                                <template v-if="registro.motivo"><span class="xl:hidden">Motivo: </span>{{ registro.motivo }}</template>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
