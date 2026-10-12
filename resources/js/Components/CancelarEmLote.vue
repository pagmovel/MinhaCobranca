<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { formatarCentavos } from '../itens.js';
import Modal from './Modal.vue';

const props = defineProps({ cobrancas: { type: Array, required: true } });
const emit = defineEmits(['fechar', 'sucesso']);

const PENDENTE = ['vencida', 'vence_hoje', 'a_vencer'];
const elegiveis = computed(() => props.cobrancas.filter((c) => PENDENTE.includes(c.situacao)));
const foraDoLote = computed(() => props.cobrancas.length - elegiveis.value.length);
const totalCentavos = computed(() => elegiveis.value.reduce((soma, c) => soma + c.valor_centavos, 0));

const confirmando = ref(false);
const form = useForm({ ids: [] });
const confirmar = () => form.transform(() => ({ ids: elegiveis.value.map((c) => c.id) }))
    .post('/cobrancas/lote/cancelar', { preserveScroll: true, onSuccess: () => emit('sucesso') });
</script>

<template>
    <Modal aberto titulo="Cancelar em lote" @fechar="emit('fechar')">
        <p class="text-sm">
            {{ elegiveis.length }} de {{ cobrancas.length }} selecionada(s) {{ elegiveis.length === 1 ? 'será cancelada' : 'serão canceladas' }}
            (R$ {{ formatarCentavos(totalCentavos) }}). O recibo de cada uma passa a sair como cancelado.
            <span v-if="foraDoLote"> {{ foraDoLote }} ficará(ão) de fora: não {{ foraDoLote === 1 ? 'está' : 'estão' }} pendente(s).</span>
        </p>
        <p v-if="form.errors.ids" class="mt-2 text-xs text-tinta-vermelha">{{ form.errors.ids }}</p>
        <div class="mt-4 flex gap-2">
            <button
                type="button"
                :class="confirmando ? 'botao-perigo-confirmar' : 'botao-perigo'"
                :disabled="form.processing || !elegiveis.length"
                @click="confirmando ? confirmar() : (confirmando = true)"
            >
                {{ confirmando ? 'Confirmar cancelamento' : 'Cancelar selecionadas' }}
            </button>
            <button type="button" class="botao-secundario" @click="emit('fechar')">Voltar</button>
        </div>
    </Modal>
</template>
