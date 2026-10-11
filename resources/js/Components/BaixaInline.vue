<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

// naFaixa: aberto na faixa abaixo da linha da tabela; ao fechar, avisa a tabela em vez de mostrar o botão.
const props = defineProps({ cobrancaId: { type: Number, required: true }, naFaixa: { type: Boolean, default: false } });
const emit = defineEmits(['fechar']);

const aberto = ref(props.naFaixa);
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
const form = useForm({ pago_em: hoje });

const fechar = () => { aberto.value = false; emit('fechar'); };
const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/pagar`, { preserveScroll: true, onSuccess: fechar });
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Dar baixa</button>
    <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="confirmar" @keydown.esc="fechar">
        <label class="flex flex-col font-estreita text-xs">
            Data do pagamento
            <input v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
        </label>
        <button class="botao" :disabled="form.processing">Confirmar baixa</button>
        <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        <p v-if="form.errors.pago_em" class="w-full text-xs text-tinta-vermelha">{{ form.errors.pago_em }}</p>
    </form>
</template>
