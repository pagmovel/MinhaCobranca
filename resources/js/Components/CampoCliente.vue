<script setup>
import { ref, watch } from 'vue';
import BuscaAutosugestao from './BuscaAutosugestao.vue';
import CadastroRapidoCliente from './CadastroRapidoCliente.vue';

/** Cliente do contrato/cobrança: escolhe na autosugestão ou cadastra ali mesmo, sem perder o formulário. */
const props = defineProps({
    form: { type: Object, required: true },
    inicial: { type: Object, default: null },
    desativado: { type: Boolean, default: false },
    opcoes: { type: Object, required: true },
});
// Avisa a página do cliente escolhido (formato da autosugestão): a descrição oferece incluir o apelido.
const emit = defineEmits(['escolhido']);

const escolhido = ref(props.inicial);
const cadastroAberto = ref(false);
const textoNovo = ref('');

watch(() => props.inicial, (cliente) => {
    escolhido.value = cliente;
});

function abrirCadastro(texto) {
    textoNovo.value = texto;
    cadastroAberto.value = true;
}

function criado(cliente) {
    escolhido.value = cliente;
    props.form.cliente_id = cliente.id;
    emit('escolhido', cliente);
}
</script>

<template>
    <BuscaAutosugestao
        id="cliente_id"
        v-model="form.cliente_id"
        :inicial="escolhido"
        :desativado="desativado"
        vazio="Clique para ver os clientes ou digite nome ou CPF/CNPJ"
        cadastrar="Cadastrar cliente"
        obrigatorio
        @cadastrar="abrirCadastro"
        @escolhido="emit('escolhido', $event)"
    />
    <CadastroRapidoCliente
        :aberto="cadastroAberto"
        :texto-inicial="textoNovo"
        :opcoes="opcoes"
        @fechar="cadastroAberto = false"
        @criado="criado"
    />
</template>
