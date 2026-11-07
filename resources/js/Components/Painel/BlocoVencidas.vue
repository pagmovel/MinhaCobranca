<script setup>
import { computed } from 'vue';
import { formatarCentavos } from '../../itens.js';
import { haQuantosDias } from '../../painelMosaico.js';
import NumeroQueConta from './NumeroQueConta.vue';

/**
 * Bloco "Vencidas" do Almanaque: quantas cobranças pendentes já passaram do vencimento (de qualquer mês), a soma e as
 * três mais antigas, cada uma um botão que abre a cobrança com as ações (`abrir`, o pai mostra o popover). Os dados são
 * `vencidas` de `GET /painel/mes`. Zerado, o número fica verde e o ponto vermelho para de pulsar.
 */
const props = defineProps({
    dados: { type: Object, default: null },
    estado: { type: String, required: true }, // 'carregando' | 'falha' | 'pronto'
});
const emit = defineEmits(['abrir']);

const vencidas = computed(() => props.dados?.vencidas ?? { quantidade: 0, soma_centavos: 0, itens: [] });
const indisponivel = computed(() => !props.dados && props.estado === 'falha'); // "—" em vez de 0: zero seria uma afirmação
const faltam = computed(() => Math.max(0, vencidas.value.quantidade - vencidas.value.itens.length));
const texto = computed(() => {
    if (!props.dados) {
        return props.estado === 'falha' ? 'Não foi possível carregar.' : 'Carregando…';
    }
    if (!vencidas.value.quantidade) {
        return 'Nenhuma vencida. Tudo em dia.';
    }

    return `${vencidas.value.quantidade === 1 ? 'cobrança vencida' : 'cobranças vencidas'}, R$\u00a0${formatarCentavos(vencidas.value.soma_centavos)}`;
});
</script>

<template>
    <section class="vencidas" :class="{ zerado: dados && !vencidas.quantidade }" aria-labelledby="titulo-vencidas" :aria-busy="!dados" tabindex="-1">
        <header>
            <h2 id="titulo-vencidas">Vencidas</h2>
            <span v-if="vencidas.quantidade" class="ponto-pulsante" aria-hidden="true" />
        </header>
        <span v-if="indisponivel" class="grande sem-dado" aria-label="indisponível">–</span>
        <NumeroQueConta v-else :centavos="vencidas.quantidade" inteiro class="grande" />
        <p class="texto">{{ texto }}</p>
        <ul v-if="vencidas.itens.length" class="pessoas">
            <li v-for="item in vencidas.itens" :key="item.id">
                <button type="button" class="pessoa" aria-haspopup="dialog" @click="emit('abrir', { id: item.id, descricao: `Vencida ${haQuantosDias(item.dias_de_atraso)}` }, $event)">
                    <b>{{ item.cliente }}</b>
                    <span>R$ {{ formatarCentavos(item.valor_centavos) }}, venceu {{ haQuantosDias(item.dias_de_atraso) }}</span>
                </button>
            </li>
            <li v-if="faltam" class="texto">e mais {{ faltam }}</li>
        </ul>
    </section>
</template>

<style scoped>
.sem-dado { opacity: .45; }
.vencidas { display: flex; flex-direction: column; height: 100%; padding: 24px; border: 1.5px solid color-mix(in srgb, var(--color-tinta-vermelha) 28%, var(--color-superficie)); border-radius: var(--raio-bloco); background: var(--color-superficie); }
.vencidas:focus { outline: none; }
header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
h2 { font: 700 20px/1.2 var(--font-titulo); letter-spacing: -0.01em; color: var(--color-tinta-vermelha); }
.ponto-pulsante { width: 10px; height: 10px; border-radius: 50%; background: var(--color-tinta-vermelha); animation: pulsar 1.8s ease-out 4; }
@keyframes pulsar { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-tinta-vermelha) 55%, transparent); } 70%, 100% { box-shadow: 0 0 0 9px transparent; } }
.grande { align-self: flex-start; margin-left: -4px; padding: 0 4px; font: 800 clamp(64px, 6.4vw, 92px) / 0.9 var(--font-numeros); letter-spacing: -0.04em; color: var(--color-tinta-vermelha); }
.zerado .grande { color: var(--color-tinta-verde); }
.texto { margin-top: 12px; font-size: 15px; color: var(--color-apagado); }
.pessoas { margin-top: auto; padding-top: 16px; display: flex; flex-direction: column; gap: 8px; }
.pessoas .texto { margin: 4px 0 0; }
.pessoa { width: 100%; min-height: 52px; display: flex; flex-direction: column; justify-content: center; gap: 1px; padding: 8px 16px; border: 0; border-radius: calc(var(--raio-bloco) * 0.6); background: color-mix(in srgb, var(--color-tinta-vermelha) 8%, var(--color-superficie)); color: inherit; font: inherit; text-align: left; cursor: pointer; transition: background-color .15s, transform .1s; }
.pessoa:hover { background: color-mix(in srgb, var(--color-tinta-vermelha) 14%, var(--color-superficie)); }
.pessoa:active { transform: scale(.98); }
.pessoa b { font-size: 14px; font-weight: 600; }
.pessoa span { font-size: 13px; color: var(--color-apagado); }
@media (prefers-reduced-motion: reduce) {
    .vencidas *, .vencidas *::before, .vencidas *::after { animation: none !important; transition: none !important; }
}
</style>
