<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    registros: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
});

const filtro = reactive({ ...props.filtros });

// Aplica ao escolher a data (padrão de usabilidade 7).
const filtrar = () => router.get('/auditoria', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <h1 class="mb-2 text-2xl font-semibold">Auditoria</h1>
        <p class="mb-6 max-w-3xl text-sm text-grafite/80">
            Correções e desfazimentos de baixa, cancelamentos, edições de cobrança e encerramentos ou correções de contrato:
            quem fez, quando e por quê. Os registros não podem ser alterados.
        </p>

        <form class="mb-6 grid max-w-xl gap-4 sm:grid-cols-2" @submit.prevent="filtrar">
            <Campo id="de" rotulo="De">
                <input id="de" v-model="filtro.de" type="date" class="entrada" @change="filtrar">
            </Campo>
            <Campo id="ate" rotulo="Até">
                <input id="ate" v-model="filtro.ate" type="date" class="entrada" :min="filtro.de" @change="filtrar">
            </Campo>
        </form>

        <p v-if="!registros.length" class="text-sm text-grafite/80">Nenhuma ação registrada neste período.</p>
        <TransitionGroup v-else name="lista" tag="ul" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="registro in registros" :key="registro.id" class="grid gap-1 px-4 py-3 sm:grid-cols-[9rem_1fr]">
                <span class="font-estreita text-sm text-grafite/75 tabular-nums">{{ registro.quando }}</span>
                <div class="min-w-0">
                    <p>
                        <Link v-if="registro.link" :href="registro.link" class="underline">{{ registro.resumo }}</Link>
                        <template v-else>{{ registro.resumo }}</template>
                    </p>
                    <p v-if="registro.motivo" class="text-sm text-grafite/80">Motivo: {{ registro.motivo }}</p>
                </div>
            </li>
        </TransitionGroup>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
