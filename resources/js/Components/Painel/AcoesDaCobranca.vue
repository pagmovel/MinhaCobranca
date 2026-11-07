<script setup>
import { computed } from 'vue';
import BaixaInline from '../BaixaInline.vue';
import BotaoEnviar from '../BotaoEnviar.vue';
import PagamentoInline from '../PagamentoInline.vue';

/**
 * As ações de uma cobrança conforme a etapa (a linha de `/painel/dados` ou de `/painel/cobrancas/{id}`): as mesmas que o
 * Painel empilhado põe nos slots das três listas. A composição Régua usa este componente nas listas e no popover do ponto.
 */
const props = defineProps({
    cobranca: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const etapa = computed(() => {
    if (props.cobranca.situacao === 'paga') {
        return 'recebida';
    }

    return props.cobranca.enviado ? 'aguardando' : 'enviar';
});
</script>

<template>
    <template v-if="etapa === 'enviar'">
        <BotaoEnviar v-if="cobranca.recibo" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
    </template>
    <template v-else-if="etapa === 'aguardando'">
        <BaixaInline :cobranca-id="cobranca.id" :valor-centavos="cobranca.valor_centavos" :vencimento-iso="cobranca.vencimento_iso" :regras="cobranca.regras" />
        <PagamentoInline :cobranca-id="cobranca.id" :pagamento="cobranca.pagamento" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
        <BotaoEnviar v-if="cobranca.recibo" rotulo="Reenviar" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
    </template>
    <BotaoEnviar v-else-if="cobranca.recibo" rotulo="Enviar recibo pago" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
</template>
