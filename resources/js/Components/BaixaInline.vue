<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ cobrancaId: { type: Number, required: true } });

const aberto = ref(false);
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
const form = useForm({ pago_em: hoje });

const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/pagar`, {
    preserveScroll: true,
    onSuccess: () => { aberto.value = false; },
});
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Dar baixa</button>
    <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="confirmar">
        <label class="flex flex-col font-estreita text-xs">
            Data do pagamento
            <input v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
        </label>
        <button class="botao" :disabled="form.processing">Confirmar baixa</button>
        <button type="button" class="botao-secundario" @click="aberto = false">Voltar</button>
        <p v-if="form.errors.pago_em" class="w-full text-xs text-tinta-vermelha">{{ form.errors.pago_em }}</p>
    </form>
</template>
