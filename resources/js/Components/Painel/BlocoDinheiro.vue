<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { fatiasDoMes } from '../../fatiasDoMes.js';
import { somasPorEtapa } from '../../linhaDoTempo.js';
import { arcosDoAnel, textoDoAnel } from '../../painelMosaico.js';
import NumeroQueConta from './NumeroQueConta.vue';

/**
 * Bloco "Dinheiro do mês" do Almanaque: um anel com a parte recebida, a que aguarda e a que falta enviar (as fatias de
 * `fatiasDoMes`, sobre os pontos de `GET /painel/mes`, regime de caixa), o recebido por extenso e a legenda. Cada item
 * da legenda leva à aba da etapa (`ir`). O anel se desenha quando os dados chegam e dá um pulso quando o recebido
 * cresce depois de uma baixa; `prefers-reduced-motion` desliga os dois.
 */
const props = defineProps({
    dados: { type: Object, default: null },
    estado: { type: String, required: true }, // 'carregando' | 'falha' | 'pronto'
    nomeDoMes: { type: String, required: true },
});
const emit = defineEmits(['ir']);

const ORDEM = ['recebida', 'aguardando', 'enviar'];
const indisponivel = computed(() => !props.dados && props.estado === 'falha'); // "—" em vez de R$ 0,00: zero seria uma afirmação
const LEGENDA = [
    { chave: 'recebida', rotulo: 'Recebido', soma: 'recebido' },
    { chave: 'aguardando', rotulo: 'Aguardando', soma: 'aguardando' },
    { chave: 'enviar', rotulo: 'Para enviar', soma: 'enviar' },
];

const somas = computed(() => (props.dados ? somasPorEtapa(props.dados.pontos) : { recebido: 0, aguardando: 0, enviar: 0, previsto: 0 }));
const fatias = computed(() => fatiasDoMes({ recebido: somas.value.recebido, aguardando: somas.value.aguardando, paraEnviar: somas.value.enviar }));
const arcos = computed(() => arcosDoAnel(fatias.value));
const porcentoRecebido = computed(() => fatias.value[0]?.porcento ?? 0);
const descricaoDoAnel = computed(() => textoDoAnel({
    porcento: porcentoRecebido.value,
    recebidoCentavos: somas.value.recebido,
    previstoCentavos: somas.value.previsto,
    nomeDoMes: props.nomeDoMes,
}));

// Sem arco (mês vazio ou carregando) o traço tem comprimento 0; ao chegar o dado a transição de CSS o desenha.
function estiloDoArco(chave, indice) {
    const arco = arcos.value.find((candidato) => candidato.chave === chave) ?? { comprimento: 0, deslocamento: 0 };

    return { strokeDasharray: `${arco.comprimento} ${100 - arco.comprimento}`, strokeDashoffset: arco.deslocamento, transitionDelay: `${indice * 220}ms` };
}

const pulso = ref(false);
let temporizador = null;
watch(() => props.dados, (novo, antigo) => {
    // `antigo` nulo é a primeira carga: o anel se desenha, mas não pulsa.
    if (novo && antigo && somasPorEtapa(novo.pontos).recebido > somasPorEtapa(antigo.pontos).recebido && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        pulso.value = false;
        clearTimeout(temporizador);
        requestAnimationFrame(() => { pulso.value = true; });
        temporizador = setTimeout(() => { pulso.value = false; }, 1100);
    }
});
onBeforeUnmount(() => clearTimeout(temporizador));
</script>

<template>
    <section class="dinheiro" aria-labelledby="titulo-dinheiro" :aria-busy="!dados" tabindex="-1">
        <h2 id="titulo-dinheiro">Dinheiro de {{ nomeDoMes }}</h2>
        <div class="corpo">
            <div class="anel-caixa" :class="{ pulso }">
                <svg class="anel" viewBox="0 0 220 220" role="img" :aria-label="descricaoDoAnel">
                    <circle class="trilho" cx="110" cy="110" r="90" />
                    <circle v-for="(chave, indice) in ORDEM" :key="chave" class="arco" :class="`arco-${chave}`" cx="110" cy="110" r="90" pathLength="100" :style="estiloDoArco(chave, indice)" />
                </svg>
                <div class="centro" aria-hidden="true">
                    <span v-if="indisponivel" class="porcento sem-dado" aria-hidden="true">–</span>
                    <NumeroQueConta v-else :centavos="porcentoRecebido" inteiro sufixo="%" class="porcento" />
                    <span>recebido</span>
                </div>
                <span class="onda" aria-hidden="true" />
            </div>
            <div class="texto">
                <p class="rotulo">Já entrou no caixa</p>
                <span v-if="indisponivel" class="valor sem-dado" aria-label="indisponível">–</span>
                <NumeroQueConta v-else :centavos="somas.recebido" class="valor" />
                <p class="previsto">
                    de <span v-if="indisponivel" class="previsto-valor">–</span><NumeroQueConta v-else :centavos="somas.previsto" class="previsto-valor" /> previstos
                </p>
                <ul class="legenda">
                    <li v-for="item in LEGENDA" :key="item.chave">
                        <button type="button" :aria-label="`Ver ${item.rotulo.toLowerCase()}`" @click="emit('ir', item.chave)">
                            <i :class="`ponto-${item.chave}`" aria-hidden="true" />
                            <span>{{ item.rotulo }}</span>
                            <span v-if="indisponivel" class="legenda-valor">–</span>
                            <NumeroQueConta v-else :centavos="somas[item.soma]" class="legenda-valor" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sem-dado { opacity: .45; }
.dinheiro { display: flex; flex-direction: column; gap: 14px; height: 100%; padding: 24px 28px; border-radius: var(--raio-bloco); background: var(--color-carbono); color: var(--color-sobre-acao); box-shadow: 0 34px 50px -30px color-mix(in srgb, var(--color-carbono) 80%, transparent); }
.dinheiro:focus { outline: none; }
.dinheiro :focus-visible { outline: 3px solid var(--color-ouro); outline-offset: 2px; }
h2 { font: 700 20px/1.2 var(--font-titulo); letter-spacing: -0.01em; }
.corpo { flex: 1; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 22px 32px; }
.anel-caixa { position: relative; flex: none; width: 208px; aspect-ratio: 1; }
.anel { display: block; width: 100%; height: auto; transform: rotate(-90deg); overflow: visible; }
.anel circle { fill: none; stroke-width: 22; }
.trilho { stroke: color-mix(in srgb, var(--color-sobre-acao) 16%, transparent); }
.arco { stroke-dasharray: 0 100; stroke-dashoffset: 0; transition: stroke-dasharray 1.4s cubic-bezier(.2,.8,.2,1), stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1); }
.arco-recebida { stroke: var(--color-anel-recebida); }
.arco-aguardando { stroke: var(--color-anel-aguardando); }
.arco-enviar { stroke: var(--color-anel-enviar); }
.centro { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
.porcento { font: 800 clamp(40px, 4vw, 52px) / 1 var(--font-numeros); letter-spacing: -0.03em; }
.centro span:last-child { margin-top: 4px; font-size: 14px; color: color-mix(in srgb, var(--color-sobre-acao) 88%, transparent); }
.onda { position: absolute; inset: 0; border-radius: 50%; border: 3px solid color-mix(in srgb, var(--color-sobre-acao) 75%, transparent); opacity: 0; pointer-events: none; }
.anel-caixa.pulso { animation: pulso-anel .8s cubic-bezier(.3,1.5,.5,1); }
.anel-caixa.pulso .onda { animation: onda .95s ease-out; }
@keyframes pulso-anel { 40% { transform: scale(1.07); } }
@keyframes onda { 0% { opacity: .9; transform: scale(.8); } 100% { opacity: 0; transform: scale(1.4); } }

.texto { flex: 1 1 190px; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.rotulo { font-size: 14px; color: color-mix(in srgb, var(--color-sobre-acao) 88%, transparent); }
.valor { align-self: flex-start; margin: 0 -4px; padding: 0 4px; font: 800 clamp(30px, 3.2vw, 40px) / 1.05 var(--font-numeros); letter-spacing: -0.03em; }
.previsto { font-size: 15px; color: color-mix(in srgb, var(--color-sobre-acao) 92%, transparent); }
.previsto-valor { font: 700 15px var(--font-numeros); }
.legenda { margin-top: 12px; display: flex; flex-direction: column; }
.legenda button { width: 100%; min-height: 44px; display: flex; align-items: center; gap: 10px; padding: 0 4px; border: 0; border-top: 1px solid color-mix(in srgb, var(--color-sobre-acao) 20%, transparent); background: none; color: inherit; font: inherit; font-size: 14px; text-align: left; cursor: pointer; transition: background-color .15s; }
.legenda button:hover { background: color-mix(in srgb, var(--color-sobre-acao) 9%, transparent); }
.legenda i { width: 12px; height: 12px; flex: none; border-radius: 50%; }
.ponto-recebida { background: var(--color-anel-recebida); }
.ponto-aguardando { background: var(--color-anel-aguardando); }
.ponto-enviar { background: var(--color-anel-enviar); }
.legenda span { flex: 1; white-space: nowrap; }
.legenda-valor { font: 700 16px var(--font-numeros); }

@container (min-width: 940px) {
    .anel-caixa { width: clamp(168px, 19cqi, 232px); }
    .valor { font-size: clamp(28px, 3cqi, 40px); }
}
@media (max-width: 440px) {
    .dinheiro { padding: 20px 18px; }
    .anel-caixa { width: 196px; }
}
@media (prefers-reduced-motion: reduce) {
    .dinheiro *, .dinheiro *::before, .dinheiro *::after { animation: none !important; transition: none !important; }
}
</style>
