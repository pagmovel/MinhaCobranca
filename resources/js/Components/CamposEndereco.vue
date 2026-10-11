<script setup>
import { ref, watch } from 'vue';
import Campo from './Campo.vue';
import { consultar, preencherVazios } from '../consultas.js';

const props = defineProps({
    form: { type: Object, required: true },
    ufs: { type: Array, required: true },
    obrigatorio: { type: Boolean, default: false },
});

const aviso = ref('');
const digitos = (texto) => String(texto ?? '').replace(/\D/g, '');
let ultimoCep = digitos(props.form.cep); // o CEP que já veio salvo não é consultado de novo

watch(() => digitos(props.form.cep), async (cep) => {
    if (cep.length !== 8 || cep === ultimoCep) {
        return;
    }
    ultimoCep = cep;
    aviso.value = '';

    const { dados, mensagem } = await consultar(`/consultas/cep/${cep}`);
    if (!dados) {
        aviso.value = mensagem;
        return;
    }
    preencherVazios(props.form, dados, { logradouro: 'logradouro', bairro: 'bairro', cidade: 'cidade', uf: 'uf' });
});
</script>

<template>
    <fieldset class="grid gap-4 sm:grid-cols-6">
        <legend class="mb-2 text-sm font-semibold">Endereço</legend>
        <Campo id="cep" rotulo="CEP (preenche o endereço)" :erro="form.errors.cep" class="sm:col-span-2">
            <input id="cep" v-model="form.cep" v-mascara="'cep'" inputmode="numeric" autocomplete="postal-code" class="entrada" :required="obrigatorio">
        </Campo>
        <p v-if="aviso" role="status" class="self-end text-xs text-grafite/80 sm:col-span-4">{{ aviso }}</p>
        <Campo id="logradouro" rotulo="Logradouro" :erro="form.errors.logradouro" class="sm:col-span-4 sm:col-start-1">
            <input id="logradouro" v-model="form.logradouro" autocomplete="address-line1" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="numero" rotulo="Número" :erro="form.errors.numero" class="sm:col-span-2">
            <input id="numero" v-model="form.numero" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="complemento" rotulo="Complemento" :erro="form.errors.complemento" class="sm:col-span-3">
            <input id="complemento" v-model="form.complemento" autocomplete="address-line2" class="entrada">
        </Campo>
        <Campo id="bairro" rotulo="Bairro" :erro="form.errors.bairro" class="sm:col-span-3">
            <input id="bairro" v-model="form.bairro" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="cidade" rotulo="Cidade" :erro="form.errors.cidade" class="sm:col-span-4">
            <input id="cidade" v-model="form.cidade" autocomplete="address-level2" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="uf" rotulo="UF" :erro="form.errors.uf" class="sm:col-span-2">
            <select id="uf" v-model="form.uf" class="entrada" :required="obrigatorio">
                <option value="">—</option>
                <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
            </select>
        </Campo>
    </fieldset>
</template>
