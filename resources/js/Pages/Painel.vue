<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';
import CoachDeConfiguracao from '../Components/CoachDeConfiguracao.vue';
import PainelEmpilhado from '../Components/Painel/PainelEmpilhado.vue';
import { componenteDoPainel } from '../temas.js';
import { usarTema } from '../usarTema.js';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
    vencimentosEmDiaNaoUtil: { type: Number, required: true },
    contratosParaRenovar: { type: Number, required: true },
    diasAntecedencia: { type: Number, required: true },
    diasAviso: { type: Number, required: true },
    modelosMensagem: { type: Array, required: true },
    prestadorNome: { type: String, required: true },
    resumo: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

// computed(), não uma const direta: page.props.progresso é substituído a cada visita do Inertia, e só um
// computed continua reativo a essa troca.
const progresso = computed(() => usePage().props.progresso);

// Nome da composição (de temas.js) para o componente. shortcut: `mosaico` e `regua` entram nas tarefas 6.1 e 7.3;
// até lá `componenteDoPainel` devolve esses nomes e o `?? PainelEmpilhado` cai no Talão.
const composicoes = { empilhado: PainelEmpilhado };
const { atual } = usarTema();
const composicao = computed(() => composicoes[componenteDoPainel(atual.value?.painel)] ?? PainelEmpilhado);
</script>

<template>
    <CamadaApp>
        <CoachDeConfiguracao v-if="progresso" :progresso="progresso" />
        <component :is="composicao" v-bind="$props" />
    </CamadaApp>
</template>
