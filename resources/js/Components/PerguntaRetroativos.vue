<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

/** Início no passado: pergunta se gera as cobranças dos períodos que já terminaram (decisão do usuário: perguntar na hora). */
const props = defineProps({
    dataInicio: { type: String, default: '' },
    periodicidade: { type: String, required: true },
    erro: { type: String, default: '' },
});

const gerar = defineModel({ type: [Boolean, null], default: null });

const anteriores = ref(null);
const carregando = ref(false);
let timer = null;
let pedido = null;

async function carregar() {
    pedido?.abort();
    const meu = new AbortController();
    pedido = meu;

    try {
        const parametros = new URLSearchParams({ data_inicio: props.dataInicio, periodicidade: props.periodicidade });
        const resposta = await fetch(`/contratos/retroativos?${parametros}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: meu.signal,
        });
        anteriores.value = resposta.ok ? await resposta.json() : null;
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            anteriores.value = null;
        }
    } finally {
        if (pedido === meu) {
            carregando.value = false;
        }
    }

    // Sem períodos anteriores, não há o que perguntar.
    if (!anteriores.value?.quantidade) {
        gerar.value = null;
    }
}

watch(() => [props.dataInicio, props.periodicidade], () => {
    clearTimeout(timer);
    anteriores.value = null;
    gerar.value = null;

    if (!props.dataInicio) {
        return;
    }

    carregando.value = true;
    timer = setTimeout(carregar, 300);
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(timer);
    pedido?.abort();
});
</script>

<template>
    <div class="sm:col-span-6" aria-live="polite">
        <p v-if="carregando" class="flex items-center gap-2 text-xs text-grafite/70">
            <span class="h-3 w-3 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
            Conferindo os períodos anteriores…
        </p>
        <Transition name="surgir">
            <fieldset v-if="anteriores?.quantidade" class="min-w-0 border border-carbono/20 bg-white p-4">
                <legend class="px-1 text-sm font-medium">
                    O início fica no passado: {{ anteriores.quantidade }}
                    {{ anteriores.quantidade === 1 ? 'período já terminou' : 'períodos já terminaram' }}
                    ({{ anteriores.de }} a {{ anteriores.ate }}).
                </legend>
                <div class="mt-1 grid gap-2 text-sm">
                    <label class="flex items-start gap-2">
                        <input v-model="gerar" type="radio" name="gerar_retroativas" :value="true" required class="mt-1">
                        <span>Gerar também essas {{ anteriores.quantidade }} cobranças (saem como vencidas, em aberto)</span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input v-model="gerar" type="radio" name="gerar_retroativas" :value="false" required class="mt-1">
                        <span>Cobrar só a partir do período atual, que começa em {{ anteriores.a_partir_de }}</span>
                    </label>
                </div>
                <p v-if="erro" class="mt-1 text-xs text-tinta-vermelha">{{ erro }}</p>
            </fieldset>
        </Transition>
    </div>
</template>
