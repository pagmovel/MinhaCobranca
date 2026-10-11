<script setup>
import { computed, ref } from 'vue';
import { consultar, preencherVazios } from '../consultas.js';

const props = defineProps({
    form: { type: Object, required: true },
    campoNome: { type: String, default: 'nome' },
});

const ENDERECO = { logradouro: 'logradouro', numero: 'numero', complemento: 'complemento', bairro: 'bairro', cidade: 'cidade', uf: 'uf', cep: 'cep' };

const cnpj = computed(() => String(props.form.documento ?? '').toUpperCase().replace(/[^0-9A-Z]/g, ''));
const buscando = ref(false);
const aviso = ref('');

async function buscar() {
    buscando.value = true;
    aviso.value = '';
    const { dados, mensagem } = await consultar(`/consultas/cnpj/${cnpj.value}`);
    buscando.value = false;

    if (!dados) {
        aviso.value = mensagem;
        return;
    }

    const mapa = { nome: props.campoNome, ...ENDERECO, ...('nome_fantasia' in props.form ? { nome_fantasia: 'nome_fantasia' } : {}) };
    const total = preencherVazios(props.form, dados, mapa);
    aviso.value = total === 0
        ? 'Os campos já estavam preenchidos; nada foi alterado.'
        : `${total === 1 ? '1 campo preenchido' : `${total} campos preenchidos`} com os dados públicos do CNPJ. Confira antes de salvar.`;
}
</script>

<template>
    <div v-if="cnpj.length === 14" class="flex flex-wrap items-center gap-3 sm:col-span-6">
        <button type="button" class="botao-secundario" :disabled="buscando" @click="buscar">
            {{ buscando ? 'Buscando…' : 'Buscar dados do CNPJ' }}
        </button>
        <p v-if="aviso" role="status" class="text-sm text-grafite/80">{{ aviso }}</p>
    </div>
</template>
