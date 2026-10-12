<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { formatarCentavos } from '../itens.js';
import Modal from './Modal.vue';

// `cobrancas`: linhas selecionadas na tela (id, valor_centavos, situacao). Previstas/pagas/canceladas não têm
// o que baixar: a prévia já separa isso, sem exigir que a seleção em si seja "limpa" primeiro.
const props = defineProps({ cobrancas: { type: Array, required: true } });
const emit = defineEmits(['fechar', 'sucesso']);

const PENDENTE = ['vencida', 'vence_hoje', 'a_vencer'];
const elegiveis = computed(() => props.cobrancas.filter((c) => PENDENTE.includes(c.situacao)));
const foraDoLote = computed(() => props.cobrancas.length - elegiveis.value.length);
const aindaNaoVenceram = computed(() => elegiveis.value.filter((c) => c.situacao === 'a_vencer').length);
const totalCentavos = computed(() => elegiveis.value.reduce((soma, c) => soma + c.valor_centavos, 0));

const modo = ref('vencimento');
const form = useForm({ ids: [], modo: 'vencimento' });
const confirmar = () => form.transform(() => ({ ids: elegiveis.value.map((c) => c.id), modo: modo.value }))
    .post('/cobrancas/lote/pagar', { preserveScroll: true, onSuccess: () => emit('sucesso') });
</script>

<template>
    <Modal aberto titulo="Dar baixa em lote" @fechar="emit('fechar')">
        <p class="text-sm">
            {{ elegiveis.length }} de {{ cobrancas.length }} selecionada(s) {{ elegiveis.length === 1 ? 'será baixada' : 'serão baixadas' }}
            (R$ {{ formatarCentavos(totalCentavos) }}).
            <span v-if="foraDoLote"> {{ foraDoLote }} ficará(ão) de fora: não {{ foraDoLote === 1 ? 'está' : 'estão' }} pendente(s).</span>
        </p>
        <fieldset class="mt-4 grid gap-2">
            <legend class="text-sm font-semibold">Data da baixa</legend>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="modo" type="radio" value="vencimento" class="accent-carbono">
                Usar a data de vencimento de cada cobrança
                <span v-if="aindaNaoVenceram" class="text-xs text-grafite/70">
                    ({{ aindaNaoVenceram }} ainda não venceu{{ aindaNaoVenceram === 1 ? '' : 'ram' }}: usa hoje nessa(s))
                </span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="modo" type="radio" value="hoje" class="accent-carbono">
                Usar a data de hoje para todas
            </label>
        </fieldset>
        <p v-if="form.errors.ids" class="mt-2 text-xs text-tinta-vermelha">{{ form.errors.ids }}</p>
        <div class="mt-4 flex gap-2">
            <button type="button" class="botao" :disabled="form.processing || !elegiveis.length" @click="confirmar">Confirmar baixa</button>
            <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
        </div>
    </Modal>
</template>
