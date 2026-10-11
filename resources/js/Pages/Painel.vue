<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';
import ListaDoPainel from '../Components/ListaDoPainel.vue';
import BotaoEnviar from '../Components/BotaoEnviar.vue';
import BaixaInline from '../Components/BaixaInline.vue';
import PagamentoInline from '../Components/PagamentoInline.vue';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
    vencimentosEmDiaNaoUtil: { type: Number, required: true },
    diasAntecedencia: { type: Number, required: true },
    resumo: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">{{ mes }}</h1>
        <p class="mt-1 text-lg tabular-nums">R$ {{ resumo.recebido }} recebidos de R$ {{ resumo.previsto }} previstos</p>

        <p v-if="!perfilCompleto" class="mt-4 border border-carbono bg-white px-4 py-3 text-sm">
            Complete seu perfil para emitir recibos.
            <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
        </p>
        <p v-if="vencimentosEmDiaNaoUtil" class="mt-4 border border-carbono bg-white px-4 py-3 text-sm">
            {{ vencimentosEmDiaNaoUtil === 1 ? '1 cobrança em aberto vence' : `${vencimentosEmDiaNaoUtil} cobranças em aberto vencem` }}
            em sábado, domingo ou feriado.
            <Link href="/perfil#feriados" class="font-semibold text-carbono underline">Ver e ajustar</Link>
        </p>

        <!-- Cada lista carrega por conta própria (/painel/dados); depois de uma ação, as três recarregam. -->
        <ListaDoPainel
            v-slot="{ cobranca }"
            lista="para_enviar"
            titulo="Para enviar"
            :vazio="`Nada para enviar agora. Cobranças novas aparecem aqui ${diasAntecedencia} dias antes do vencimento.`"
        >
            <BotaoEnviar v-if="cobranca.recibo" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>

        <ListaDoPainel v-slot="{ cobranca }" lista="aguardando" titulo="Aguardando pagamento" vazio="Nenhuma cobrança enviada esperando pagamento.">
            <BaixaInline :cobranca-id="cobranca.id" />
            <PagamentoInline :cobranca-id="cobranca.id" :pagamento="cobranca.pagamento" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            <BotaoEnviar v-if="cobranca.recibo" rotulo="Reenviar" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>

        <ListaDoPainel v-slot="{ cobranca }" lista="recebidas" titulo="Recebidas no mês" vazio="Nenhum pagamento registrado neste mês.">
            <BotaoEnviar v-if="cobranca.recibo" rotulo="Enviar recibo pago" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>
    </CamadaApp>
</template>
