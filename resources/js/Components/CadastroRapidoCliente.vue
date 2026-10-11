<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Campo from './Campo.vue';
import BuscaCnpj from './BuscaCnpj.vue';
import CamposEndereco from './CamposEndereco.vue';
import { enviarJson } from '../http.js';

/** Cliente novo sem sair do contrato/cobrança: o que já foi preenchido lá continua intacto. */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    textoInicial: { type: String, default: '' },
    opcoes: { type: Object, required: true },
});

const emit = defineEmits(['fechar', 'criado']);

const vazio = {
    nome: '', apelido: '', documento: '', email: '', telefone: '', modelo: 'webiss',
    cep: '', logradouro: '', numero: '', complemento: '', bairro: '', cidade: '', uf: '',
};
const form = useForm({ ...vazio });
const aviso = ref('');

watch(() => props.aberto, (aberto) => {
    if (!aberto) {
        return;
    }
    Object.assign(form, vazio, { modelo: props.opcoes.modeloPadrao });
    form.clearErrors();
    aviso.value = '';
    // Digitou números na busca? É o CPF/CNPJ. Senão, é o nome.
    const digitos = props.textoInicial.replace(/[^0-9A-Za-z]/g, '');
    if (/\d{3,}/.test(digitos) && digitos.length >= 11) {
        form.documento = props.textoInicial;
    } else {
        form.nome = props.textoInicial;
    }
});

async function salvar() {
    form.processing = true;
    aviso.value = '';
    const { dados, erros, mensagem } = await enviarJson('/clientes', form.data());
    form.processing = false;

    if (dados) {
        emit('criado', dados);
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
    <Modal :aberto="aberto" titulo="Novo cliente" largura="max-w-2xl" @fechar="emit('fechar')">
        <form class="grid gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="rapido_cliente_documento" rotulo="CPF ou CNPJ" :erro="form.errors.documento" class="sm:col-span-3">
                <input id="rapido_cliente_documento" v-model="form.documento" v-mascara="'documento'" class="entrada" required :autofocus="!!form.nome">
            </Campo>
            <Campo id="rapido_cliente_nome" rotulo="Nome ou razão social" :erro="form.errors.nome" class="sm:col-span-3">
                <input id="rapido_cliente_nome" v-model="form.nome" maxlength="255" class="entrada" required :autofocus="!form.nome">
            </Campo>
            <Campo id="rapido_cliente_apelido" rotulo="Apelido (opcional)" :erro="form.errors.apelido" class="sm:col-span-6">
                <input id="rapido_cliente_apelido" v-model="form.apelido" maxlength="60" class="entrada" aria-describedby="rapido_cliente_apelido_ajuda">
                <p id="rapido_cliente_apelido_ajuda" class="font-estreita text-xs text-grafite/70">
                    Nome curto para as telas e buscas (ex.: Regional Araci); permite outra unidade com o mesmo CPF/CNPJ.
                </p>
            </Campo>
            <BuscaCnpj :form="form" />
            <Campo id="rapido_cliente_email" rotulo="E-mail (opcional)" :erro="form.errors.email" class="sm:col-span-3">
                <input id="rapido_cliente_email" v-model="form.email" v-mascara="'minusculas'" type="email" class="entrada">
            </Campo>
            <Campo id="rapido_cliente_telefone" rotulo="Telefone (opcional)" :erro="form.errors.telefone" class="sm:col-span-3">
                <input id="rapido_cliente_telefone" v-model="form.telefone" v-mascara="'telefone'" inputmode="tel" type="tel" class="entrada">
            </Campo>
            <fieldset class="sm:col-span-6">
                <legend class="mb-2 font-estreita text-xs font-medium text-grafite/75">Modelo do recibo</legend>
                <div class="flex flex-wrap gap-4">
                    <label v-for="modelo in opcoes.modelos" :key="modelo.valor" class="flex items-center gap-2 text-sm">
                        <input v-model="form.modelo" type="radio" name="rapido_cliente_modelo" :value="modelo.valor"> {{ modelo.rotulo }}
                    </label>
                </div>
            </fieldset>
            <details class="sm:col-span-6">
                <summary class="cursor-pointer text-sm font-semibold text-carbono">Endereço (opcional; o CEP preenche o resto)</summary>
                <div class="mt-3">
                    <CamposEndereco :form="form" :ufs="opcoes.ufs" />
                </div>
            </details>
            <p v-if="aviso" role="alert" class="text-sm text-tinta-vermelha sm:col-span-6">{{ aviso }}</p>
            <div class="flex flex-wrap gap-2 sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Cadastrar e usar' }}</button>
                <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
            </div>
        </form>
    </Modal>
</template>
