<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';
import LinhaCobranca from '../Components/LinhaCobranca.vue';
import BotaoEnviar from '../Components/BotaoEnviar.vue';
import BaixaInline from '../Components/BaixaInline.vue';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
    diasAntecedencia: { type: Number, required: true },
    resumo: { type: Object, required: true },
    paraEnviar: { type: Array, required: true },
    aguardando: { type: Array, required: true },
    recebidas: { type: Array, required: true },
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

        <section class="mt-8" aria-labelledby="bloco-enviar">
            <h2 id="bloco-enviar" class="mb-2 text-lg font-semibold">Para enviar <span class="font-estreita text-grafite/70">({{ paraEnviar.length }})</span></h2>
            <p v-if="!paraEnviar.length" class="text-sm text-grafite/80">
                Nada para enviar agora. Cobranças novas aparecem aqui {{ diasAntecedencia }} dias antes do vencimento.
            </p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in paraEnviar" :key="cobranca.id" :cobranca="cobranca">
                    <BotaoEnviar v-if="cobranca.recibo" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="bloco-aguardando">
            <h2 id="bloco-aguardando" class="mb-2 text-lg font-semibold">Aguardando pagamento <span class="font-estreita text-grafite/70">({{ aguardando.length }})</span></h2>
            <p v-if="!aguardando.length" class="text-sm text-grafite/80">Nenhuma cobrança enviada esperando pagamento.</p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in aguardando" :key="cobranca.id" :cobranca="cobranca">
                    <BaixaInline :cobranca-id="cobranca.id" />
                    <BotaoEnviar v-if="cobranca.recibo" rotulo="Reenviar" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="bloco-recebidas">
            <h2 id="bloco-recebidas" class="mb-2 text-lg font-semibold">Recebidas no mês <span class="font-estreita text-grafite/70">({{ recebidas.length }})</span></h2>
            <p v-if="!recebidas.length" class="text-sm text-grafite/80">Nenhum pagamento registrado neste mês.</p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in recebidas" :key="cobranca.id" :cobranca="cobranca">
                    <BotaoEnviar v-if="cobranca.recibo" rotulo="Enviar recibo pago" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>
    </CamadaApp>
</template>
