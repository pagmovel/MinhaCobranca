<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    contratoId: { type: Number, required: true },
    processando: { type: Boolean, default: false },
    erro: { type: String, default: '' }, // erro de validação da confirmação (POST), vindo do pai
});

const emit = defineEmits(['confirmar', 'voltar']);

const novaData = ref('');
const previa = ref(null);
const carregando = ref(true);
const erroPrevia = ref('');

const minimo = (() => {
    const amanha = new Date();
    amanha.setDate(amanha.getDate() + 1);
    return amanha.toLocaleDateString('sv-SE');
})();

onMounted(async () => {
    try {
        const resposta = await fetch(`/contratos/${props.contratoId}/renovacao`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!resposta.ok) {
            erroPrevia.value = 'Não foi possível montar a prévia.';
            return;
        }
        previa.value = await resposta.json();
    } catch {
        erroPrevia.value = 'Não foi possível montar a prévia.';
    } finally {
        carregando.value = false;
    }
});

const TEXTOS = {
    reabrir: 'Volta a cobrar',
    proporcional_cheio: 'Volta ao valor cheio',
};

function oQueAcontece(acao) {
    return acao.acao === 'aviso_avulsa'
        ? 'Já foi enviada como proporcional: revise manualmente, isto não muda sozinho.'
        : TEXTOS[acao.acao];
}

const confirmar = () => emit('confirmar', { data_fim: novaData.value || null });
</script>

<template>
    <div class="aparecer grid gap-3 border border-carbono/40 bg-white p-4">
        <label class="grid max-w-xs gap-1 text-sm">
            <span class="font-estreita text-xs font-medium text-grafite/75">Nova data de fim (vazio = sem fim)</span>
            <input v-model="novaData" type="date" :min="minimo" class="entrada">
        </label>
        <p v-if="carregando" class="text-sm text-grafite/70">Montando a prévia…</p>
        <Transition name="surgir">
            <p v-if="erroPrevia" class="text-sm text-tinta-vermelha">{{ erroPrevia }}</p>
        </Transition>
        <template v-if="previa && !erroPrevia">
            <p v-if="!previa.acoes.length" class="text-sm text-grafite/80">Nenhuma cobrança para ajustar.</p>
            <ul v-else class="grid gap-1 text-sm">
                <li v-for="(acao, indice) in previa.acoes" :key="indice" :class="{ 'text-tinta-vermelha': acao.acao === 'aviso_avulsa' }">
                    {{ acao.periodo }} (R$ {{ acao.valor }}): {{ oQueAcontece(acao) }}
                </li>
            </ul>
        </template>
        <Transition name="surgir">
            <p v-if="erro" class="text-sm text-tinta-vermelha">{{ erro }}</p>
        </Transition>
        <div class="mt-2 flex gap-3">
            <button type="button" class="botao" :disabled="processando || carregando || !previa || Boolean(erroPrevia)" @click="confirmar">
                Confirmar renovação
            </button>
            <button type="button" class="botao-secundario" @click="emit('voltar')">Voltar</button>
        </div>
    </div>
</template>
