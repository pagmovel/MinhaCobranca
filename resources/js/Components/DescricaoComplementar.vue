<script setup>
import { computed, nextTick, ref } from 'vue';
import Campo from './Campo.vue';
import { apelidoDoCliente, comApelidoNaDescricao, descricaoComecaCom } from '../apelido.js';

/**
 * Descrição complementar do contrato/cobrança. Com cliente de apelido escolhido, oferece pôr o apelido na primeira
 * linha (o recibo não mostra o apelido sozinho; decisão do usuário, 2026-10-11). Opcional: dá para editar ou apagar.
 */
const props = defineProps({
    form: { type: Object, required: true },
    cliente: { type: Object, default: null },
});

const campo = ref(null);
const apelido = computed(() => apelidoDoCliente(props.cliente, props.form.cliente_id));
const oferecer = computed(() => apelido.value !== '' && !descricaoComecaCom(props.form.descricao, apelido.value));

function incluir() {
    props.form.descricao = comApelidoNaDescricao(props.form.descricao, apelido.value);
    nextTick(() => campo.value?.focus());
}
</script>

<template>
    <Campo id="descricao" rotulo="Descrição complementar (opcional)" :erro="form.errors.descricao" class="sm:col-span-6">
        <textarea id="descricao" ref="campo" v-model="form.descricao" rows="3" class="entrada" placeholder="Ex.: domínio, mês de referência, número do contrato, observações" />
        <p class="mt-1 text-xs text-grafite/70">No recibo, sai abaixo da lista de itens. Itens e descrição cabem em até 25 linhas.</p>
        <Transition name="surgir">
            <button v-if="oferecer" type="button" class="botao-secundario self-start text-sm" @click="incluir">
                Incluir o apelido na descrição ({{ apelido }})
            </button>
        </Transition>
    </Campo>
</template>
