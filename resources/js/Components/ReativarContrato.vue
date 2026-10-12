<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    contratoId: { type: Number, required: true },
});

const previa = ref(null);
const carregando = ref(true);
const erro = ref('');

onMounted(async () => {
    try {
        const resposta = await fetch(`/contratos/${props.contratoId}/reativacao`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!resposta.ok) {
            erro.value = 'Não foi possível montar a prévia.';
            return;
        }
        previa.value = await resposta.json();
    } catch {
        erro.value = 'Não foi possível montar a prévia.';
    } finally {
        carregando.value = false;
    }
});

const TEXTOS = {
    reabrir: 'Volta a cobrar',
    proporcional_cheio: 'Volta ao valor cheio',
    desfazer_avulsa: 'Cancela a avulsa (enviada como proporcional) e volta a cobrar o valor cheio aqui',
};

function oQueAcontece(acao) {
    return acao.acao === 'aviso_avulsa'
        ? 'Já foi enviada como proporcional e paga como avulsa: revise manualmente, isto não muda sozinho.'
        : TEXTOS[acao.acao];
}
</script>

<template>
    <div class="aparecer grid gap-2 border border-carbono/40 bg-white p-4 text-sm">
        <p class="font-medium">O que "Salvar contrato" vai desfazer</p>
        <p v-if="carregando" class="text-grafite/70">Montando a prévia…</p>
        <Transition name="surgir">
            <p v-if="erro" class="text-tinta-vermelha">{{ erro }}</p>
        </Transition>
        <template v-if="previa && !erro">
            <p v-if="!previa.acoes.length" class="text-grafite/80">Nenhuma cobrança para ajustar.</p>
            <ul v-else class="grid gap-1">
                <li v-for="(acao, indice) in previa.acoes" :key="indice" :class="{ 'text-tinta-vermelha': acao.acao === 'aviso_avulsa' }">
                    {{ acao.periodo }} (R$ {{ acao.valor }}): {{ oQueAcontece(acao) }}
                </li>
            </ul>
        </template>
    </div>
</template>
