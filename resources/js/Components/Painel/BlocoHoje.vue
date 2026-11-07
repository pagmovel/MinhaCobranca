<script setup>
import { computed } from 'vue';
import { formatarCentavos } from '../../itens.js';
import { tipoDoPonto } from '../../linhaDoTempo.js';
import { notaDoDia } from '../../painelMosaico.js';
import { textosDoPainel } from '../../textosDoPainel.js';
import NumeroQueConta from './NumeroQueConta.vue';

/**
 * Bloco "Hoje" do Almanaque: o que vence hoje (quantas, a soma e as três primeiras, cada uma um botão que abre a
 * cobrança com as ações) e dois atalhos de trabalho, "Cobranças a avisar" e "Recibos a enviar", que levam à aba certa (`ir`).
 * Os dados são `hoje_resumo` e os pontos de `GET /painel/mes`.
 */
const props = defineProps({
    dados: { type: Object, default: null },
    estado: { type: String, required: true }, // 'carregando' | 'falha' | 'pronto'
});
const emit = defineEmits(['abrir', 'ir']);

const indisponivel = computed(() => !props.dados && props.estado === 'falha'); // "—" em vez de 0: zero seria uma afirmação
const venceHoje = computed(() => props.dados?.hoje_resumo.vence_hoje ?? { quantidade: 0, soma_centavos: 0 });
const nomes = computed(() => (props.dados?.pontos ?? []).filter((ponto) => tipoDoPonto(ponto) === 'hoje').slice(0, 3));
const faltam = computed(() => Math.max(0, venceHoje.value.quantidade - nomes.value.length));
const afazeres = computed(() => [
    { rotulo: textosDoPainel.avisar, valor: props.dados?.hoje_resumo.para_avisar ?? 0, etapa: 'aguardando' },
    { rotulo: textosDoPainel.enviar.titulo, valor: props.dados?.hoje_resumo.para_enviar ?? 0, etapa: 'enviar' },
]);
const texto = computed(() => {
    if (!props.dados) {
        return props.estado === 'falha' ? 'Não foi possível carregar.' : 'Carregando…';
    }
    if (!venceHoje.value.quantidade) {
        return 'Nada vence hoje.';
    }

    return `${venceHoje.value.quantidade === 1 ? 'cobrança vence' : 'cobranças vencem'} hoje, R$\u00a0${formatarCentavos(venceHoje.value.soma_centavos)}`;
});
</script>

<template>
    <section class="hoje" aria-labelledby="titulo-hoje" :aria-busy="!dados" tabindex="-1">
        <header>
            <h2 id="titulo-hoje">Hoje</h2>
            <span v-if="dados" class="nota">{{ notaDoDia(dados) }}</span>
        </header>
        <span v-if="indisponivel" class="grande sem-dado" aria-label="indisponível">–</span>
        <NumeroQueConta v-else :centavos="venceHoje.quantidade" inteiro class="grande" />
        <p class="texto">{{ texto }}</p>
        <ul v-if="nomes.length" class="pessoas">
            <li v-for="ponto in nomes" :key="ponto.id">
                <button type="button" class="pessoa" aria-haspopup="dialog" @click="emit('abrir', { id: ponto.id, descricao: 'Vence hoje' }, $event)">
                    <b>{{ ponto.cliente }}</b>
                    <span>R$ {{ formatarCentavos(ponto.valor_centavos) }}</span>
                </button>
            </li>
            <li v-if="faltam" class="texto">e mais {{ faltam }}</li>
        </ul>
        <ul class="afazeres">
            <li v-for="afazer in afazeres" :key="afazer.rotulo">
                <button type="button" class="afazer" @click="emit('ir', afazer.etapa)">
                    <span>{{ afazer.rotulo }}</span>
                    <span v-if="indisponivel" class="afazer-valor sem-dado" aria-label="indisponível">–</span>
                    <NumeroQueConta v-else :centavos="afazer.valor" inteiro class="afazer-valor" />
                </button>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.sem-dado { opacity: .45; }
.hoje { display: flex; flex-direction: column; height: 100%; padding: 24px; border-radius: var(--raio-bloco); background: var(--color-hoje-fundo); color: var(--color-grafite); }
.hoje:focus { outline: none; }
header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px; margin-bottom: 14px; }
h2 { font: 700 20px/1.2 var(--font-titulo); letter-spacing: -0.01em; color: var(--color-tinta-laranja); }
.nota { font-size: 14px; color: var(--color-tinta-laranja); }
.grande { align-self: flex-start; margin-left: -4px; padding: 0 4px; font: 800 clamp(64px, 6.4vw, 92px) / 0.9 var(--font-numeros); letter-spacing: -0.04em; color: var(--color-tinta-laranja); }
.texto { margin-top: 12px; font-size: 15px; }
.pessoas { padding-top: 16px; display: flex; flex-direction: column; gap: 8px; }
.pessoas .texto { margin: 4px 0 0; }
.pessoa, .afazer { width: 100%; border: 0; color: inherit; font: inherit; text-align: left; cursor: pointer; background: color-mix(in srgb, var(--color-superficie) 72%, transparent); transition: background-color .15s, transform .1s; }
.pessoa:hover, .afazer:hover { background: var(--color-superficie); }
.pessoa:active, .afazer:active { transform: scale(.98); }
.pessoa { min-height: 52px; display: flex; flex-direction: column; justify-content: center; gap: 1px; padding: 8px 16px; border-radius: calc(var(--raio-bloco) * 0.6); }
.pessoa b { font-size: 14px; font-weight: 600; }
.pessoa span { font-size: 13px; }
.afazeres { margin-top: auto; padding-top: 16px; display: flex; flex-direction: column; gap: 8px; }
.afazer { min-height: 46px; display: flex; align-items: center; gap: 10px; padding: 0 8px 0 16px; border-radius: calc(var(--raio-bloco) * 0.6); font-size: 14px; font-weight: 500; }
.afazer > span:first-child { flex: 1; }
.afazer-valor { min-width: 32px; height: 32px; padding: 0 8px; display: grid; place-items: center; border-radius: calc(var(--raio-bloco) * 0.45); background: var(--color-tinta-laranja); color: var(--color-superficie); font: 700 16px var(--font-numeros); }
@media (prefers-reduced-motion: reduce) {
    .hoje *, .hoje *::before, .hoje *::after { animation: none !important; transition: none !important; }
}
</style>
