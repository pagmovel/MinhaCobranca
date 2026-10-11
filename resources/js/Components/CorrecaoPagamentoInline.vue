<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

/** Cobrança paga: corrigir a data do pagamento ou desfazer a baixa, com senha e motivo (fica na auditoria). */
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    enviada: { type: Boolean, default: false },
});

const modo = ref(null);
const hoje = new Date().toLocaleDateString('sv-SE');
const form = useForm({ pago_em: hoje, motivo: '', senha: '' });
const primeiroCampo = ref(null);

const abrir = async (qual) => {
    form.reset();
    form.clearErrors();
    modo.value = qual;
    await nextTick();
    primeiroCampo.value?.focus();
};

const confirmar = () => form.post(
    `/cobrancas/${props.cobrancaId}/${modo.value === 'corrigir' ? 'corrigir-pagamento' : 'desfazer-baixa'}`,
    {
        preserveScroll: true,
        onSuccess: () => { modo.value = null; },
        onFinish: () => form.reset('senha'),
    },
);
</script>

<template>
    <span v-if="!modo" class="inline-flex flex-wrap gap-2">
        <button type="button" class="botao-secundario" @click="abrir('corrigir')">Corrigir pagamento</button>
        <button type="button" class="botao-perigo" @click="abrir('desfazer')">Desfazer baixa</button>
    </span>
    <form v-else class="aparecer grid w-full gap-2 border border-carbono/20 bg-papel p-3 sm:grid-cols-2" @submit.prevent="confirmar" @keydown.esc="modo = null">
        <p class="text-sm font-medium sm:col-span-2">
            {{ modo === 'corrigir' ? 'Corrigir a data do pagamento' : 'Desfazer a baixa: a cobrança volta a ficar em aberto' }}
        </p>
        <label v-if="modo === 'corrigir'" class="flex flex-col font-estreita text-xs">
            Data certa do pagamento
            <input ref="primeiroCampo" v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
            <span v-if="form.errors.pago_em" class="text-tinta-vermelha">{{ form.errors.pago_em }}</span>
        </label>
        <label class="flex flex-col font-estreita text-xs" :class="{ 'sm:col-span-2': modo === 'desfazer' }">
            Motivo
            <input
                :ref="modo === 'desfazer' ? 'primeiroCampo' : undefined"
                v-model="form.motivo"
                type="text"
                maxlength="500"
                class="entrada"
                placeholder="Ex.: data digitada errada"
                required
            >
            <span v-if="form.errors.motivo" class="text-tinta-vermelha">{{ form.errors.motivo }}</span>
        </label>
        <label class="flex flex-col font-estreita text-xs">
            Sua senha (confirma que foi você)
            <input v-model="form.senha" type="password" autocomplete="current-password" class="entrada" required>
            <span v-if="form.errors.senha" class="text-tinta-vermelha">{{ form.errors.senha }}</span>
        </label>
        <p v-if="enviada && modo === 'corrigir'" class="text-xs text-grafite/80 sm:col-span-2">
            Se o recibo pago já foi enviado ao cliente, ele está com a data antiga: envie de novo depois de corrigir.
        </p>
        <div class="flex flex-wrap gap-2 sm:col-span-2">
            <button :class="modo === 'corrigir' ? 'botao' : 'botao-perigo-confirmar'" :disabled="form.processing">
                {{ modo === 'corrigir' ? 'Corrigir a data' : 'Desfazer a baixa' }}
            </button>
            <button type="button" class="botao-secundario" @click="modo = null">Voltar</button>
        </div>
    </form>
</template>
