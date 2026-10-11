<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Campo from './Campo.vue';
import { enviarJson } from '../http.js';

/** Produto ou serviço novo sem sair do contrato/cobrança: entra no catálogo e já volta para a lista de itens. */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    nomeInicial: { type: String, default: '' },
});

const emit = defineEmits(['fechar', 'criado']);

const form = useForm({ nome: '', tipo: 'servico', unidade: 'un', preco: '0,00', codigo_servico: '', codigo_nbs: '', codigo_tributacao_nacional: '' });
const aviso = ref('');

watch(() => props.aberto, (aberto) => {
    if (aberto) {
        form.reset();
        form.clearErrors();
        form.nome = props.nomeInicial;
        aviso.value = '';
    }
});

async function salvar() {
    form.processing = true;
    aviso.value = '';
    const { dados, erros, mensagem } = await enviarJson('/produtos-servicos', { ...form.data(), ativo: true });
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
    <Modal :aberto="aberto" titulo="Novo produto ou serviço" @fechar="emit('fechar')">
        <form class="grid gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="rapido_nome" rotulo="Nome" :erro="form.errors.nome" class="sm:col-span-6">
                <input id="rapido_nome" v-model="form.nome" maxlength="120" class="entrada" required autofocus>
            </Campo>
            <Campo id="rapido_tipo" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="rapido_tipo" v-model="form.tipo" class="entrada">
                    <option value="servico">Serviço</option>
                    <option value="produto">Produto</option>
                </select>
            </Campo>
            <Campo id="rapido_unidade" rotulo="Unidade" :erro="form.errors.unidade" class="sm:col-span-2">
                <input id="rapido_unidade" v-model="form.unidade" maxlength="20" class="entrada" list="rapido_unidades" required>
                <datalist id="rapido_unidades">
                    <option value="un" /><option value="mês" /><option value="hora" /><option value="ano" /><option value="serviço" />
                </datalist>
            </Campo>
            <Campo id="rapido_preco" rotulo="Preço sugerido (R$)" :erro="form.errors.preco" class="sm:col-span-2">
                <input id="rapido_preco" v-model="form.preco" v-mascara="'valor'" inputmode="decimal" class="entrada">
            </Campo>
            <p class="text-xs text-grafite/70 sm:col-span-6">
                O preço é só sugestão: no contrato ou na cobrança você digita o valor combinado com o cliente. Deixe 0,00 para
                itens sem preço fixo.
            </p>
            <details class="sm:col-span-6">
                <summary class="cursor-pointer text-sm font-semibold text-carbono">Códigos fiscais (opcional)</summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-6">
                    <Campo id="rapido_codigo_servico" rotulo="Código do serviço (LC 116)" :erro="form.errors.codigo_servico" class="sm:col-span-6">
                        <input id="rapido_codigo_servico" v-model="form.codigo_servico" class="entrada">
                    </Campo>
                    <Campo id="rapido_codigo_nbs" rotulo="Código NBS" :erro="form.errors.codigo_nbs" class="sm:col-span-3">
                        <input id="rapido_codigo_nbs" v-model="form.codigo_nbs" class="entrada">
                    </Campo>
                    <Campo id="rapido_codigo_trib" rotulo="Tributação nacional" :erro="form.errors.codigo_tributacao_nacional" class="sm:col-span-3">
                        <input id="rapido_codigo_trib" v-model="form.codigo_tributacao_nacional" class="entrada">
                    </Campo>
                </div>
            </details>
            <p v-if="aviso" role="alert" class="text-sm text-tinta-vermelha sm:col-span-6">{{ aviso }}</p>
            <div class="flex flex-wrap gap-2 sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Cadastrar e incluir' }}</button>
                <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
            </div>
        </form>
    </Modal>
</template>
