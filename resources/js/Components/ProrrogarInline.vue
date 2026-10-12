<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

// naFaixa: aberto na faixa abaixo da linha da tabela; ao fechar, avisa a tabela em vez de mostrar o botão.
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    vencimentoAtual: { type: String, required: true }, // d/m/Y
    naFaixa: { type: Boolean, default: false },
});
const emit = defineEmits(['fechar']);

const aberto = ref(props.naFaixa);

// A nova data precisa ser estritamente depois do vencimento atual (regra do servidor).
const minimo = (() => {
    const [dia, mes, ano] = props.vencimentoAtual.split('/');
    const data = new Date(`${ano}-${mes}-${dia}T12:00:00`);
    data.setDate(data.getDate() + 1);

    return data.toLocaleDateString('sv-SE');
})();

const form = useForm({ vencimento: minimo });

const fechar = () => { aberto.value = false; emit('fechar'); };
const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/prorrogar`, { preserveScroll: true, onSuccess: fechar });
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Prorrogar vencimento</button>
    <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="confirmar" @keydown.esc="fechar">
        <label class="flex flex-col font-estreita text-xs">
            Novo vencimento
            <input v-model="form.vencimento" type="date" class="entrada" :min="minimo" required>
        </label>
        <button class="botao" :disabled="form.processing">Confirmar prorrogação</button>
        <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        <p v-if="form.errors.vencimento" class="w-full text-xs text-tinta-vermelha">{{ form.errors.vencimento }}</p>
    </form>
</template>
