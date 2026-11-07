<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, provide, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { avisosDoPainel } from '../../avisosDoPainel.js';
import { frasePeloDia } from '../../linhaDoTempo.js';
import { abaPorTecla, esvaziou } from '../../painelMosaico.js';
import { usarMesDoPainel } from '../../usarMesDoPainel.js';
import BlocoVencendo from '../BlocoVencendo.vue';
import LinhaDoTempoDoMes from '../LinhaDoTempoDoMes.vue';
import ListaDoPainel from '../ListaDoPainel.vue';
import AcoesDaCobranca from './AcoesDaCobranca.vue';
import BlocoDinheiro from './BlocoDinheiro.vue';
import BlocoHoje from './BlocoHoje.vue';
import BlocoVencidas from './BlocoVencidas.vue';
import NumeroQueConta from './NumeroQueConta.vue';
import PopoverDaCobranca from './PopoverDaCobranca.vue';

// Composição do Almanaque (painel `mosaico`, variante com linha do tempo): topo com os avisos, os blocos Dinheiro,
// Vencidas e Hoje, a linha do tempo em largura total e a lista em abas deslizantes. Mesmas props da página Painel.vue
// (toda composição declara as 11, senão a que sobra cai em $attrs). Fora desta versão: "Semana à frente" e "Recebido
// por dia", que são do tema Mosaico. shortcut: só existe a variante com linha do tempo (Almanaque); o tema Mosaico,
// sem ela, pede aqui a decisão por `linha_do_tempo` do tema (prop `comLinhaDoTempo`).
const props = defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
    vencimentosEmDiaNaoUtil: { type: Number, required: true },
    contratosParaRenovar: { type: Number, required: true },
    diasAntecedencia: { type: Number, required: true },
    diasAviso: { type: Number, required: true },
    modelosMensagem: { type: Array, required: true },
    prestadorNome: { type: String, required: true },
    resumo: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const semMovimento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const { dados, estado, falha, carregar } = usarMesDoPainel();
const nomeDoMes = computed(() => props.mes.split(' ')[0]);
const fraseDeHoje = computed(() => (dados.value ? frasePeloDia(dados.value) : ''));
const avisos = computed(() => avisosDoPainel(props));

// ---------- Abas da lista ----------
// As três listas ficam montadas e só a da aba ativa aparece: assim as contagens das abas ficam certas e o que foi
// digitado numa aba (a baixa aberta numa linha) não se perde ao trocar de aba.
const ABAS = [
    { chave: 'enviar', lista: 'para_enviar', rotulo: 'Enviar', cor: 'var(--color-etapa-enviar)' },
    { chave: 'aguardando', lista: 'aguardando', rotulo: 'Aguardando', cor: 'var(--color-etapa-aguardando)' },
    { chave: 'recebida', lista: 'recebidas', rotulo: 'Recebidas', cor: 'var(--color-etapa-recebida)' },
];
const aba = ref('aguardando');
const contagens = reactive({ para_enviar: null, aguardando: null, recebidas: null });
const corDaAba = computed(() => ABAS.find((item) => item.chave === aba.value).cor);

const listaDeAbas = ref(null);
const botoesDeAba = {};
const indicador = reactive({ x: 0, largura: 0, pronto: false });
function posicionarIndicador() {
    const botao = botoesDeAba[aba.value];
    if (botao) {
        indicador.x = botao.offsetLeft;
        indicador.largura = botao.offsetWidth;
    }
}
watch(aba, () => nextTick(posicionarIndicador));

function teclaNaAba(evento) {
    const destino = abaPorTecla(evento.key, aba.value);
    if (destino) {
        evento.preventDefault();
        aba.value = destino;
        nextTick(() => botoesDeAba[destino]?.focus());
    }
}

// Leva a uma etapa (legenda do anel, atalhos do bloco Hoje): troca a aba, põe o foco na lista e traz o bloco da lista
// à vista, com as abas no topo.
async function irParaAba(etapa) {
    aba.value = etapa;
    await nextTick();
    document.getElementById(`painel-${etapa}`)?.focus({ preventScroll: true });
    blocoDaLista.value?.scrollIntoView({ block: 'start', behavior: semMovimento() ? 'auto' : 'smooth' });
}

// ---------- Efeitos de uma etapa que mudou ----------
const blocoDaLista = ref(null);
const recebeu = ref(null);
let temporizadorDaAba = null;
function registrarResumo({ lista, quantidade }) {
    const antes = contagens[lista];
    contagens[lista] = quantidade;
    if (lista !== 'recebidas' && esvaziou(antes, quantidade)) {
        confete();
    }
    if (lista === 'recebidas' && antes !== null && quantidade > antes && !semMovimento()) {
        recebeu.value = 'recebida';
        clearTimeout(temporizadorDaAba);
        temporizadorDaAba = setTimeout(() => { recebeu.value = null; }, 700);
    }
}

// Confete discreto quando "Para enviar" ou "Aguardando" chega a zero depois de uma ação: o trabalho da etapa acabou.
function confete() {
    if (semMovimento() || !blocoDaLista.value) {
        return;
    }
    const caixa = blocoDaLista.value.getBoundingClientRect();
    const estilo = getComputedStyle(document.documentElement);
    const cores = ['--color-etapa-enviar', '--color-etapa-aguardando', '--color-etapa-recebida', '--color-ouro', '--color-anel-recebida', '--color-tinta-laranja']
        .map((nome) => estilo.getPropertyValue(nome).trim());
    for (let i = 0; i < 22; i++) {
        const peca = document.createElement('i');
        const largura = 5 + Math.random() * 5;
        peca.setAttribute('aria-hidden', 'true');
        peca.style.cssText = `position:fixed;z-index:25;pointer-events:none;border-radius:2px;left:${caixa.left + 20 + Math.random() * (caixa.width - 40)}px;top:${Math.max(caixa.top, 0) + 10}px;width:${largura}px;height:${largura * 1.6}px;background:${cores[i % cores.length]}`;
        document.body.append(peca);
        const queda = 140 + Math.random() * 170;
        const desvio = (Math.random() - 0.5) * 90;
        const giro = (Math.random() - 0.5) * 720;
        peca.animate([
            { transform: 'translate(0, 0) rotate(0deg)', opacity: 1 },
            { transform: `translate(${desvio}px, ${queda * 0.65}px) rotate(${giro / 2}deg)`, opacity: 1, offset: 0.7 },
            { transform: `translate(${desvio * 1.3}px, ${queda}px) rotate(${giro}deg)`, opacity: 0 },
        ], { duration: 1000 + Math.random() * 600, delay: Math.random() * 250, easing: 'cubic-bezier(.3,.6,.5,1)', fill: 'backwards' }).onfinish = () => peca.remove();
    }
}

// ---------- Hover e foco ligam o ponto da linha do tempo e a linha da lista, nos dois sentidos ----------
const destaque = ref(null);
provide('destaqueDaCobranca', {
    id: destaque,
    ligar: (id) => { destaque.value = id; },
    desligar: (id) => { if (destaque.value === id) { destaque.value = null; } },
});

// ---------- Cobrança aberta a partir de um bloco (Vencidas e Hoje): o mesmo popover do ponto, com as ações ----------
const aberta = ref(null); // { id, descricao, ancora, bloco }
function abrirCobranca({ id, descricao }, evento) {
    const ancora = evento.currentTarget;
    aberta.value = aberta.value?.id === id ? null : { id, descricao, ancora, bloco: ancora.closest('section') };
}
// Depois de uma baixa a pessoa some do bloco: o foco volta ao bloco, nunca ao `body`.
let blocoDoFoco = null;
let temporizadorDoFoco = null;
function fecharCobranca(devolverFoco = true) {
    const { ancora, bloco } = aberta.value ?? {};
    aberta.value = null;
    if (devolverFoco) {
        blocoDoFoco = bloco;
        clearTimeout(temporizadorDoFoco);
        temporizadorDoFoco = setTimeout(() => { blocoDoFoco = null; }, 2000);
        nextTick(() => (ancora?.isConnected ? ancora : bloco)?.focus({ preventScroll: true }));
    }
}
watch(dados, async () => {
    await nextTick();
    if (blocoDoFoco && (!document.activeElement || document.activeElement === document.body)) {
        blocoDoFoco.focus({ preventScroll: true });
    }
});

let observador = null;
onMounted(() => {
    posicionarIndicador();
    observador = new ResizeObserver(posicionarIndicador);
    observador.observe(listaDeAbas.value);
    Object.values(botoesDeAba).forEach((botao) => botao && observador.observe(botao));
    document.fonts?.ready.then(posicionarIndicador);
    requestAnimationFrame(() => { indicador.pronto = true; }); // só depois da primeira medida: o indicador não "chega" de 0
});
onBeforeUnmount(() => {
    observador?.disconnect();
    clearTimeout(temporizadorDaAba);
    clearTimeout(temporizadorDoFoco);
});
</script>

<template>
    <div class="mosaico">
        <header class="topo">
            <div>
                <h1>{{ mes }}</h1>
                <p v-if="fraseDeHoje" class="frase">{{ fraseDeHoje }}</p>
            </div>
            <ul v-if="avisos.length" class="avisos">
                <li v-for="aviso in avisos" :key="aviso.href">
                    <Link :href="aviso.href" class="aviso">
                        <span>{{ aviso.texto }}</span>
                        <b>{{ aviso.acao }}</b>
                    </Link>
                </li>
            </ul>
        </header>

        <p v-if="estado === 'falha'" role="alert" class="falha-do-mes">
            <span>Não foi possível carregar os números do mês.</span>
            <button type="button" class="botao-secundario" @click="carregar">Tentar de novo</button>
        </p>

        <div class="bento-caixa">
            <div class="bento">
                <BlocoDinheiro class="area-dinheiro" :dados="dados" :estado="estado" :nome-do-mes="nomeDoMes.toLowerCase()" @ir="irParaAba" />
                <BlocoHoje class="area-hoje" :dados="dados" :estado="estado" @abrir="abrirCobranca" @ir="irParaAba" />
                <BlocoVencidas class="area-vencidas" :dados="dados" :estado="estado" @abrir="abrirCobranca" />

                <section class="bloco area-mes" aria-labelledby="titulo-mes-mosaico">
                    <h2 id="titulo-mes-mosaico">{{ nomeDoMes }}, dia a dia</h2>
                    <LinhaDoTempoDoMes
                        :dados="dados"
                        :estado="estado"
                        :falha="falha"
                        :destaque="destaque"
                        :chaves-pix="chavesPix"
                        :formas-pagamento="formasPagamento"
                        :modelos="modelosMensagem"
                        :prestador="prestadorNome"
                        @destacar="destaque = $event"
                        @tentar="carregar"
                    />
                </section>

                <section id="o-que-fazer" ref="blocoDaLista" class="bloco area-lista" aria-labelledby="titulo-lista-mosaico">
                    <header class="lista-cab">
                        <h2 id="titulo-lista-mosaico">Cobranças</h2>
                        <div
                            ref="listaDeAbas"
                            class="abas"
                            role="tablist"
                            aria-label="Etapas da cobrança"
                            :style="{ '--cor-aba': corDaAba, '--x': `${indicador.x}px`, '--l': `${indicador.largura}px` }"
                            @keydown="teclaNaAba"
                        >
                            <span class="indicador" :class="{ pronto: indicador.pronto }" aria-hidden="true" />
                            <button
                                v-for="item in ABAS"
                                :id="`aba-${item.chave}`"
                                :key="item.chave"
                                :ref="(elemento) => { botoesDeAba[item.chave] = elemento; }"
                                type="button"
                                role="tab"
                                class="aba"
                                :class="{ recebeu: recebeu === item.chave }"
                                :aria-selected="aba === item.chave"
                                :aria-controls="`painel-${item.chave}`"
                                :tabindex="aba === item.chave ? 0 : -1"
                                @click="aba = item.chave"
                            >
                                {{ item.rotulo }}
                                <NumeroQueConta v-if="contagens[item.lista] !== null" :centavos="contagens[item.lista]" inteiro class="aba-cont" />
                                <span v-else class="aba-cont" aria-hidden="true">…</span>
                            </button>
                        </div>
                    </header>

                    <!-- Cada lista carrega por conta própria (/painel/dados); depois de uma ação, as três recarregam. -->
                    <div id="painel-enviar" v-show="aba === 'enviar'" class="painel" role="tabpanel" aria-labelledby="aba-enviar" tabindex="-1">
                        <ListaDoPainel
                            v-slot="{ cobranca }"
                            lista="para_enviar"
                            titulo="Para enviar"
                            :vazio="`Nada para enviar agora. Cobranças novas aparecem aqui ${diasAntecedencia} dias antes do vencimento.`"
                            @resumo="registrarResumo"
                        >
                            <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
                        </ListaDoPainel>
                    </div>
                    <div id="painel-aguardando" v-show="aba === 'aguardando'" class="painel" role="tabpanel" aria-labelledby="aba-aguardando" tabindex="-1">
                        <BlocoVencendo :dias-aviso="diasAviso" :modelos="modelosMensagem" :prestador="prestadorNome" />
                        <ListaDoPainel
                            v-slot="{ cobranca }"
                            lista="aguardando"
                            titulo="Aguardando pagamento"
                            vazio="Nenhuma cobrança enviada esperando pagamento."
                            @resumo="registrarResumo"
                        >
                            <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
                        </ListaDoPainel>
                    </div>
                    <div id="painel-recebida" v-show="aba === 'recebida'" class="painel" role="tabpanel" aria-labelledby="aba-recebida" tabindex="-1">
                        <ListaDoPainel
                            v-slot="{ cobranca }"
                            lista="recebidas"
                            titulo="Recebidas no mês"
                            vazio="Nenhum pagamento registrado neste mês."
                            @resumo="registrarResumo"
                        >
                            <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
                        </ListaDoPainel>
                    </div>
                </section>
            </div>
        </div>

        <!-- No body, como o da linha do tempo: o popover é `fixed` e a página inteira vive dentro de `.aparecer`. -->
        <PopoverDaCobranca
            v-if="aberta"
            :key="aberta.id"
            :id="aberta.id"
            :ancora="aberta.ancora"
            :descricao="aberta.descricao"
            :chaves-pix="chavesPix"
            :formas-pagamento="formasPagamento"
            :modelos="modelosMensagem"
            :prestador="prestadorNome"
            @fechar="fecharCobranca"
        />
    </div>
</template>

<style scoped>
.mosaico { display: flex; flex-direction: column; gap: 26px; }

/* Topo: o mês em letra grande, a frase do dia e os avisos em pílulas (o ponto dourado chama o olho). */
.topo { display: flex; flex-direction: column; gap: 16px; }
h1 { font: 800 clamp(2.25rem, 4.4vw, 3.5rem) / 1 var(--font-titulo); letter-spacing: -0.03em; }
.frase { margin-top: 8px; font-size: 15px; color: var(--color-apagado); }
.avisos { display: flex; flex-wrap: wrap; gap: 10px; }
.aviso { display: inline-flex; align-items: center; gap: 10px; min-height: 44px; padding: 6px 18px 6px 14px; border: 1px solid var(--color-linha); border-radius: 999px; background: var(--color-superficie); color: var(--color-grafite); font-size: 14px; text-decoration: none; transition: border-color .15s, background-color .15s; }
.aviso:hover { border-color: var(--color-linha-forte); }
.aviso::before { content: ''; flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--color-ouro); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-ouro) 35%, transparent); }
.aviso b { color: var(--color-carbono); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; white-space: nowrap; }
.falha-do-mes { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-tinta-vermelha); border-radius: calc(var(--raio-bloco) * 0.6); background: var(--color-superficie); font-size: 14px; }

/* A grade responde à largura da área de conteúdo, não da janela. */
.bento-caixa { container-type: inline-size; }
.bento { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); grid-template-areas: 'din' 'hoje' 'venc' 'mes' 'lista'; }
.area-dinheiro { grid-area: din; }
.area-hoje { grid-area: hoje; }
.area-vencidas { grid-area: venc; }
.area-mes { grid-area: mes; }
.area-lista { grid-area: lista; }
@container (min-width: 600px) {
    .bento { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-areas: 'din din' 'hoje venc' 'mes mes' 'lista lista'; }
}
/* A grade de dias cabe na largura do bloco (o menu lateral tira 13rem da linha do tempo): de 760 px para cima os dias
   encolhem de 38 até 33 px; abaixo disso vale o tamanho da própria linha do tempo, que rola dentro do bloco. */
@container (min-width: 760px) {
    .area-mes :deep(.tempo) { --dia: clamp(33px, calc((100cqi - 152px) / var(--dias)), 38px); }
    .area-mes :deep(.pt) { font-size: 12px; letter-spacing: -0.03em; }
}
@container (min-width: 940px) {
    .bento { gap: 20px; grid-template-columns: repeat(12, minmax(0, 1fr)); grid-template-areas:
        'din din din din din din venc venc venc hoje hoje hoje'
        'mes mes mes mes mes mes mes mes mes mes mes mes'
        'lista lista lista lista lista lista lista lista lista lista lista lista'; }
}

.bloco { min-width: 0; padding: 24px clamp(14px, 1.8vw, 24px); border: 1px solid var(--color-linha); border-radius: var(--raio-bloco); background: var(--color-superficie); box-shadow: var(--sombra-bloco); }
.bloco > h2 { margin-bottom: 14px; font: 700 20px/1.2 var(--font-titulo); letter-spacing: -0.01em; }
.area-lista { isolation: isolate; padding-bottom: 14px; }

/* Abas da lista: o indicador desliza até a aba escolhida e leva a cor da etapa. */
.lista-cab { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px 24px; }
.lista-cab h2 { font: 700 24px/1.2 var(--font-titulo); letter-spacing: -0.01em; }
.abas { position: relative; display: flex; gap: 2px; max-width: 100%; padding: 4px; border-radius: calc(var(--raio-bloco) * 0.65); background: var(--color-papel); }
.indicador { position: absolute; top: 4px; bottom: 4px; left: 0; width: var(--l); transform: translateX(var(--x)); border-radius: calc(var(--raio-bloco) * 0.5); background: var(--cor-aba); box-shadow: 0 10px 18px -10px var(--cor-aba); }
.indicador.pronto { transition: transform .5s cubic-bezier(.3,1.3,.4,1), width .5s cubic-bezier(.3,1.3,.4,1), background-color .3s; }
.aba { position: relative; z-index: 1; flex: 1 1 auto; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 14px; border: 0; border-radius: calc(var(--raio-bloco) * 0.5); background: none; color: var(--color-apagado); font: inherit; font-size: 14px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: color .25s; }
.aba:not([aria-selected='true']):hover { color: var(--color-grafite); }
.aba[aria-selected='true'] { color: var(--color-sobre-acao); }
.aba:focus-visible { outline: 3px solid var(--color-grafite); outline-offset: 2px; }
.aba-cont { min-width: 26px; height: 26px; padding: 0 7px; display: grid; place-items: center; border-radius: 13px; background: color-mix(in srgb, var(--color-grafite) 9%, transparent); font: 700 14px var(--font-numeros); transition: background-color .25s; }
.aba[aria-selected='true'] .aba-cont { background: color-mix(in srgb, var(--color-grafite) 24%, transparent); }
.aba.recebeu { animation: recebeu .6s cubic-bezier(.3,1.6,.5,1); }
@keyframes recebeu { 40% { transform: scale(1.14); } }
@container (max-width: 520px) {
    .abas { width: 100%; }
    .aba { flex-direction: column; gap: 2px; min-height: 54px; padding: 6px 4px; font-size: 13px; }
    .aba-cont { min-width: 24px; height: 22px; padding: 0 6px; font-size: 13px; }
}

/* As listas são as do Talão; aqui só perdem a moldura quadrada para caber no bloco arredondado. */
.painel { margin-top: 6px; }
.painel:focus { outline: none; }
.painel :deep(section) { margin-top: 20px; }
.painel :deep(h2) { margin-bottom: 6px; font-family: var(--font-titulo); font-size: 20px; font-weight: 700; letter-spacing: -0.01em; }
.painel :deep(h2 span) { font-family: var(--font-sans); font-size: 14px; font-weight: 500; letter-spacing: 0; color: var(--color-apagado); }
.painel :deep(ul[id^='lista-']) { border-width: 0; background: transparent; }
.painel :deep(div[aria-busy='true']) { overflow: hidden; border-radius: calc(var(--raio-bloco) * 0.5); }
.painel :deep(p[role='status'].border) { border: 1.5px dashed var(--color-linha-forte); border-radius: calc(var(--raio-bloco) * 0.6); background: transparent; color: var(--color-apagado); text-align: center; padding: 26px 16px; }

@media (prefers-reduced-motion: reduce) {
    .mosaico *, .mosaico *::before, .mosaico *::after { animation: none !important; transition: none !important; }
}
</style>
