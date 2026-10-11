<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CamposPagamento from './CamposPagamento.vue';

const props = defineProps({
    cobrancaId: { type: Number, required: true },
    pagamento: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    naFaixa: { type: Boolean, default: false },
});
const emit = defineEmits(['fechar']);

const aberto = ref(props.naFaixa);
const form = useForm({ ...props.pagamento });

const fechar = () => { aberto.value = false; emit('fechar'); };
const salvar = () => form.put(`/cobrancas/${props.cobrancaId}/pagamento`, { preserveScroll: true, onSuccess: fechar });
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Forma de pagamento</button>
    <form v-else class="grid w-full gap-3 sm:grid-cols-6" @submit.prevent="salvar" @keydown.esc="fechar">
        <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" com-linha-digitavel />
        <div class="flex flex-wrap gap-2 sm:col-span-6">
            <button class="botao" :disabled="form.processing">Salvar forma de pagamento</button>
            <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        </div>
    </form>
</template>
