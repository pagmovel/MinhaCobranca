<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Campo from './Campo.vue';
import { enviarJson } from '../http.js';

/** Chave PIX nova sem sair do contrato/cobrança (antes era preciso ir ao perfil e perder o formulário). */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    tipos: { type: Array, required: true },
});

const emit = defineEmits(['fechar', 'criada']);

const mascaras = { cpf: 'cpf', cnpj: 'cnpj', telefone: 'telefone', email: 'minusculas', aleatoria: 'minusculas' };
const form = useForm({ tipo: 'cpf', chave: '', apelido: '' });
const aviso = ref('');

watch(() => props.aberto, (aberto) => {
    if (aberto) {
        form.reset();
        form.clearErrors();
        aviso.value = '';
    }
});

async function salvar() {
    form.processing = true;
    aviso.value = '';
    const { dados, erros, mensagem } = await enviarJson('/perfil/chaves-pix', form.data());
    form.processing = false;

    if (dados) {
        emit('criada', dados);
        emit('fechar');
    } else if (erros) {
        form.clearErrors();
        form.setError(erros);
    } else {
        aviso.value = mensagem;
    }
}
</script>

<template>
    <Modal :aberto="aberto" titulo="Nova chave PIX" @fechar="emit('fechar')">
        <form class="grid gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="rapido_tipo_chave" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="rapido_tipo_chave" v-model="form.tipo" class="entrada">
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="rapido_chave" rotulo="Chave" :erro="form.errors.chave" class="sm:col-span-4">
                <input id="rapido_chave" v-model="form.chave" v-mascara="mascaras[form.tipo]" class="entrada" required autofocus>
            </Campo>
            <Campo id="rapido_apelido" rotulo="Apelido (opcional, ex.: Nubank PJ)" :erro="form.errors.apelido" class="sm:col-span-6">
                <input id="rapido_apelido" v-model="form.apelido" maxlength="60" class="entrada">
            </Campo>
            <p class="text-xs text-grafite/70 sm:col-span-6">A chave também fica no seu perfil para os próximos contratos e cobranças.</p>
            <p v-if="aviso" role="alert" class="text-sm text-tinta-vermelha sm:col-span-6">{{ aviso }}</p>
            <div class="flex flex-wrap gap-2 sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Cadastrar e usar' }}</button>
                <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
            </div>
        </form>
    </Modal>
</template>
