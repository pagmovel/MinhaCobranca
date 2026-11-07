<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { formatarCentavos } from '../itens.js';
import {
    acumuladoPorDia, caminhosDaRegua, colunasDoMes, descricaoDoPonto, diaDaSemana, linhasDaGrade, ordemDeTabulacao,
    NIVEIS_POR_DIA, posicaoDoDia, previstoPorDia, tipoDoPonto, valorCompacto, vizinhoDoPonto,
} from '../linhaDoTempo.js';
import PopoverDaCobranca from './Painel/PopoverDaCobranca.vue';

/**
 * Linha do tempo do mês corrente (dados de `GET /painel/mes`, vindos do pai): eixo de dias, um ponto por cobrança
 * (vencimentos acima do eixo, pagamentos abaixo), régua de dinheiro com o previsto pontilhado e o marcador "Anteriores".
 * Cada ponto é um botão: Tab os percorre, as setas andam entre eles e o clique abre o popover com as ações da cobrança.
 * Hover e foco do ponto acendem a linha da lista (`destacar`) e a linha acesa da lista acende o ponto (`destaque`).
 * Quando os dados mudam depois de uma ação, o ponto que mudou de lugar salta até o dia novo. Nada se mexe com
 * `prefers-reduced-motion`. A página nunca rola sozinha: só o bloco rola, e só de lado.
 */
const props = defineProps({
    dados: { type: Object, default: null },
    estado: { type: String, required: true }, // 'carregando' | 'falha' | 'pronto'
    falha: { type: String, default: '' },
    filtro: { type: String, default: null }, // 'enviar' | 'aguardando' | 'recebida': esmaece as outras etapas
    destaque: { type: Number, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});
const emit = defineEmits(['destacar', 'tentar']);

const semMovimento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const rolagem = ref(null);
const grade = ref(null);
const arcos = ref(null);
const ponta = ref(null);

const dias = computed(() => props.dados?.dias ?? 30);
const hoje = computed(() => props.dados?.hoje ?? 1);
const colunas = computed(() => (props.dados ? colunasDoMes(props.dados.pontos, props.dados.dias) : []));
const ordem = computed(() => ordemDeTabulacao(colunas.value));
const anteriores = computed(() => props.dados?.anteriores ?? { quantidade: 0, soma_centavos: 0 });

// A altura das faixas só cresce: o eixo não pula de lugar quando alguém paga e a faixa de cima esvazia.
const linhasDeVencimento = ref(2);
const linhasDePagamento = ref(1);
watch(colunas, (lista) => {
    // A pílula "+N" ocupa um nível da pilha.
    linhasDeVencimento.value = Math.max(linhasDeVencimento.value, ...lista.map((coluna) => coluna.vencimentos.length + (coluna.maisVencimentos ? 1 : 0)));
    linhasDePagamento.value = Math.max(linhasDePagamento.value, ...lista.map((coluna) => coluna.pagamentos.length + (coluna.maisPagamentos ? 1 : 0)));
}, { immediate: true });

const regua = computed(() => {
    const d = props.dados;

    return d ? caminhosDaRegua({
        recebido: acumuladoPorDia(d.pontos, d.dias),
        previsto: previstoPorDia(d.pontos, d.dias, d.hoje),
        dias: d.dias,
        hoje: d.hoje,
    }) : null;
});
const moedaCompacta = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', notation: 'compact', maximumFractionDigits: 1 });
const linhasDeReferencia = computed(() => (regua.value
    ? linhasDaGrade(regua.value.maximo).map((valor) => ({ topo: 100 - (valor / regua.value.maximo) * 100, rotulo: moedaCompacta.format(valor / 100) }))
    : []));
const metaDoMes = computed(() => (regua.value ? `R$ ${formatarCentavos(regua.value.maximo)} previstos` : ''));

const feriadosDoMes = computed(() => Object.entries(props.dados?.feriados ?? {}).map(([dia, nome]) => `${dia} ${nome}`).join(', '));
const sombraDoDia = (dia) => (props.dados?.feriados?.[dia] ? 'feriado' : props.dados?.fim_de_semana.includes(dia) ? 'fim-de-semana' : null);

const SEMANA_CURTA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const semanaDoDia = (dia) => SEMANA_CURTA[diaDaSemana(props.dados.mes, dia)];
const feriadoDo = (dia) => props.dados.feriados[dia];
const tipo = tipoDoPonto;
const textoDoPonto = (ponto) => descricaoDoPonto(ponto, props.dados);
const rotuloDoPonto = (ponto) => `${ponto.cliente}, R$ ${formatarCentavos(ponto.etapa === 'recebida' ? ponto.pago_centavos : ponto.valor_centavos)}. ${textoDoPonto(ponto)}`;
const rotuloDoMais = (quantidade) => `${quantidade} cobranças a mais neste dia; veja nas listas abaixo`;
const textoDoMais = (quantidade) => `+${quantidade > 999 ? '999+' : quantidade}`;
const valorDoPonto = (ponto) => (ponto.etapa === 'recebida' ? ponto.pago_centavos : ponto.valor_centavos);

// ---------- Abertura: a linha se desenha e os pontos caem (uma vez só) ----------
const abrindo = ref(true);
let temporizadorDaAbertura = null;
watch(() => props.estado, (estado) => {
    if (estado === 'pronto' && !temporizadorDaAbertura) {
        mostrarHoje();
        temporizadorDaAbertura = setTimeout(() => { abrindo.value = false; }, 6500);
    }
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(temporizadorDaAbertura));

// Em tela estreita o bloco rola de lado: abre já com "hoje" à vista (só o bloco rola, nunca a página).
async function mostrarHoje() {
    await nextTick();
    const caixa = rolagem.value;
    if (caixa && caixa.scrollWidth > caixa.clientWidth) {
        const calha = caixa.querySelector('.calha')?.offsetWidth ?? 0;
        const noEixo = calha + posicaoDoDia(hoje.value, dias.value) * (caixa.scrollWidth - calha);
        caixa.scrollLeft = Math.max(0, noEixo - caixa.clientWidth * 0.4);
    }
}

// ---------- Dica ao passar o mouse ou focar ----------
const dica = ref(null); // { ponto, estilo }
function mostrarDica(ponto, botao) {
    const r = botao.getBoundingClientRect();
    const largura = Math.min(300, window.innerWidth - 16);
    dica.value = {
        ponto,
        estilo: {
            left: `${Math.min(Math.max(8, r.left + r.width / 2 - largura / 2), window.innerWidth - largura - 8)}px`,
            // Perto do topo da janela a dica desce para baixo do ponto.
            ...(r.top > 130 ? { bottom: `${window.innerHeight - r.top + 10}px` } : { top: `${r.bottom + 10}px` }),
            maxWidth: `${largura}px`,
        },
    };
}
const esconderDica = () => { dica.value = null; };

let emitidoPorAqui = null; // o destaque que este componente mesmo acabou de pedir: não precisa trazer o ponto à vista
function entrouNoPonto(ponto, evento) {
    emitidoPorAqui = ponto.id;
    emit('destacar', ponto.id);
    if (aberto.value?.ponto.id !== ponto.id && !focoPelaCodigo) {
        mostrarDica(ponto, evento.currentTarget);
    }
}
function saiuDoPonto() {
    emitidoPorAqui = null;
    emit('destacar', null);
    esconderDica();
}

// ---------- Popover do ponto ----------
const aberto = ref(null); // { ponto, ancora }
function abrir(ponto, evento) {
    esconderDica();
    aberto.value = aberto.value?.ponto.id === ponto.id ? null : { ponto, ancora: evento.currentTarget };
}
// O foco volta ao ponto pelo id, não pelo elemento: depois de uma baixa o botão é outro (o ponto mudou de faixa) e só
// existe quando os dados novos chegam; por isso o pedido fica pendente por 2 s e `aplicarFoco` roda também depois deles.
let focoPendente = null;
let temporizadorDoFoco = null;
let focoPelaCodigo = false;
function aplicarFoco(encerrar = false) {
    if (focoPendente !== null) {
        focoPelaCodigo = true;
        botaoDoPonto(focoPendente)?.focus({ preventScroll: true });
        focoPelaCodigo = false;
        if (encerrar === true) {
            focoPendente = null; // os dados novos já chegaram e o botão certo existe
        }
    }
}
function fecharPopover(devolverFoco = true) {
    const id = aberto.value?.ponto.id;
    aberto.value = null;
    if (devolverFoco && id !== undefined) {
        focoPendente = id;
        clearTimeout(temporizadorDoFoco);
        temporizadorDoFoco = setTimeout(() => { focoPendente = null; }, 2000);
        nextTick(aplicarFoco);
    }
}
onBeforeUnmount(() => clearTimeout(temporizadorDoFoco));
// O ponto muda de lugar (e de elemento) quando a etapa muda: o popover segue o botão novo do mesmo id.
watch(() => props.dados, async () => {
    if (!aberto.value) {
        return;
    }
    await nextTick();
    const botao = botaoDoPonto(aberto.value.ponto.id);
    const ponto = props.dados?.pontos.find((candidato) => candidato.id === aberto.value.ponto.id);
    aberto.value = botao && ponto ? { ponto, ancora: botao } : null;
});
watch(() => props.dados, async () => {
    await nextTick();
    aplicarFoco(true);
});

// ---------- Teclado: as setas andam entre os pontos, na ordem do Tab ----------
const botaoDoPonto = (id) => rolagem.value?.querySelector(`[data-ponto="${id}"]`);
function trazerParaVista(botao) {
    const caixa = rolagem.value;
    if (!caixa || !botao) {
        return;
    }
    const limite = caixa.getBoundingClientRect();
    const alvo = botao.getBoundingClientRect();
    const calha = caixa.querySelector('.calha')?.offsetWidth ?? 0;
    const comportamento = semMovimento() ? 'auto' : 'smooth';
    if (alvo.left < limite.left + calha + 8) {
        caixa.scrollBy({ left: alvo.left - limite.left - calha - 28, behavior: comportamento });
    } else if (alvo.right > limite.right - 8) {
        caixa.scrollBy({ left: alvo.right - limite.right + 28, behavior: comportamento });
    }
}
function teclaNoPonto(evento, ponto) {
    const passos = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 };
    let id = null;
    if (evento.key in passos) {
        id = vizinhoDoPonto(ordem.value, ponto.id, passos[evento.key]);
    } else if (evento.key === 'Home' || evento.key === 'End') {
        id = evento.key === 'Home' ? ordem.value[0] : ordem.value.at(-1);
    } else {
        return;
    }
    evento.preventDefault();
    const botao = botaoDoPonto(id);
    botao?.focus({ preventScroll: true });
    trazerParaVista(botao);
}

// A linha da lista ganhou o foco ou o mouse: se o ponto está fora da parte visível do bloco, traz o ponto.
watch(() => props.destaque, (id) => {
    if (id !== null && id !== emitidoPorAqui) {
        trazerParaVista(botaoDoPonto(id));
    }
});

// ---------- Salto do ponto: o que mudou de lugar depois de uma ação voa com arco até o lugar novo ----------
let antes = new Map();
watch(() => props.dados, () => {
    // flush 'pre': roda antes do Vue redesenhar, quando os botões ainda estão nos lugares antigos.
    antes = new Map();
    const base = grade.value?.getBoundingClientRect();
    rolagem.value?.querySelectorAll('[data-ponto]').forEach((botao) => {
        const r = botao.getBoundingClientRect();
        const estilo = getComputedStyle(botao);
        // Relativo à grade (não à janela): uma rolagem entre as duas medidas não vira salto.
        antes.set(Number(botao.dataset.ponto), {
            x: r.left + r.width / 2 - base.left, y: r.top + r.height / 2 - base.top,
            fundo: estilo.backgroundColor, cor: estilo.color, borda: estilo.borderTopColor, etapa: botao.dataset.etapa,
        });
    });
}, { flush: 'pre' });
watch(() => props.dados, async () => {
    await nextTick();
    if (semMovimento() || !antes.size) {
        return;
    }
    const base = grade.value.getBoundingClientRect();
    for (const botao of rolagem.value.querySelectorAll('[data-ponto]')) {
        const anterior = antes.get(Number(botao.dataset.ponto));
        if (!anterior) {
            continue;
        }
        const r = botao.getBoundingClientRect();
        const para = { x: r.left + r.width / 2 - base.left, y: r.top + r.height / 2 - base.top };
        const dx = anterior.x - para.x;
        const dy = anterior.y - para.y;
        if (Math.hypot(dx, dy) > 4) {
            // Mudou de etapa ou de dia: salta com arco. Só desceu um degrau na pilha do mesmo dia: desliza.
            if (anterior.etapa !== botao.dataset.etapa || Math.abs(dx) > 8) {
                voar(botao, dx, dy, { x: anterior.x, y: anterior.y }, para);
            } else {
                botao.animate({ translate: [`0px ${dy}px`, '0px 0px'] }, { duration: 450, easing: 'cubic-bezier(.2,.8,.2,1)' });
            }
        } else if (anterior.etapa !== botao.dataset.etapa) {
            mudarDeCor(botao, anterior);
        }
        if (anterior.etapa !== 'recebida' && botao.dataset.etapa === 'recebida') {
            reiniciar(ponta.value, 'pisca');
        }
    }
    antes = new Map();
});

function voar(botao, dx, dy, de, para) {
    const bojo = Math.abs(dx) < 8 ? 40 : 0;
    const meioY = 0.25 * dy + 0.5 * (Math.min(de.y, para.y) - 72 - para.y);
    const duracao = 700;
    botao.style.zIndex = 8;
    botao.animate({ translate: [`${dx}px 0px`, `${0.5 * dx + 0.5 * bojo}px 0px`, '0px 0px'], offset: [0, 0.5, 1] }, { duration: duracao, easing: 'linear' });
    botao.animate(
        { transform: [`translateY(${dy}px)`, `translateY(${meioY}px)`, 'translateY(0)'], offset: [0, 0.5, 1], easing: ['cubic-bezier(.2,.6,.4,1)', 'cubic-bezier(.6,0,.8,.4)'] },
        { duration: duracao },
    ).finished.then(() => { botao.style.zIndex = ''; }, () => {});
    desenharArco(de, para, bojo);
    if (botao.dataset.etapa === 'recebida') {
        botao.classList.add('pt-novo');
        setTimeout(() => botao.classList.remove('pt-novo'), 1800);
    }
}

function desenharArco(de, para, bojo) {
    const caminho = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    caminho.setAttribute('d', `M${de.x} ${de.y} Q${(de.x + para.x) / 2 + bojo} ${Math.min(de.y, para.y) - 72} ${para.x} ${para.y}`);
    caminho.setAttribute('pathLength', '1');
    caminho.classList.add('arco');
    arcos.value.append(caminho);
    caminho.animate([{ strokeDashoffset: 1 }, { strokeDashoffset: 0 }], { duration: 650, easing: 'cubic-bezier(.3,.7,.4,1)', fill: 'both' });
    caminho.animate([{ opacity: 0.6 }, { opacity: 0 }], { delay: 2200, duration: 900, fill: 'both' }).finished.then(() => caminho.remove(), () => caminho.remove());
}

function mudarDeCor(botao, anterior) {
    const agora = getComputedStyle(botao);
    botao.animate([{ backgroundColor: anterior.fundo, color: anterior.cor, borderColor: anterior.borda }, { backgroundColor: agora.backgroundColor, color: agora.color, borderColor: agora.borderTopColor }], { duration: 650, easing: 'ease-out' });
    botao.animate([{ transform: 'scale(.8)' }, { transform: 'scale(1.2)', offset: 0.45 }, { transform: 'scale(1)' }], { duration: 650 });
}

function reiniciar(elemento, classe) {
    elemento?.classList.remove(classe);
    void elemento?.offsetWidth;
    elemento?.classList.add(classe);
}

function irParaAsListas() {
    // Vai até as listas, a pedido de quem clicou (a vencida de mês passado e os pontos escondidos pelo "+N" estão nas listas de baixo).
    document.getElementById('o-que-fazer')?.scrollIntoView({ block: 'start', behavior: semMovimento() ? 'auto' : 'smooth' });
}
</script>

<template>
    <div>
        <div v-if="estado === 'carregando'" class="esqueleto" aria-busy="true">
            <p role="status" class="text-sm text-apagado">Carregando a linha do tempo…</p>
        </div>
        <div v-else-if="estado === 'falha'" role="alert" class="falha">
            <span>{{ falha }}</span>
            <button type="button" class="botao-secundario" @click="emit('tentar')">Tentar de novo</button>
        </div>
        <template v-else>
            <div v-if="falha" role="alert" class="falha falha-leve">
                <span>{{ falha }} Os dados abaixo podem estar desatualizados.</span>
                <button type="button" class="botao-secundario" @click="emit('tentar')">Tentar de novo</button>
            </div>
            <div
                ref="rolagem"
                class="rolagem"
                role="region"
                aria-label="Linha do tempo do mês. Role para os lados para ver todos os dias"
                @scroll.passive="esconderDica"
            >
                <div
                    class="tempo"
                    :class="{ abrindo, 'sem-anteriores': !anteriores.quantidade }"
                    :style="{ '--dias': dias, '--n-venc': linhasDeVencimento, '--n-pag': linhasDePagamento }"
                    :data-filtro="filtro || undefined"
                >
                    <div class="calha">
                        <span class="rotulo-faixa rotulo-venc">Vencimentos</span>
                        <button
                            v-if="anteriores.quantidade"
                            type="button"
                            class="anteriores"
                            :aria-label="`Anteriores: ${anteriores.quantidade} ${anteriores.quantidade === 1 ? 'cobrança vencida' : 'cobranças vencidas'} de meses passados, R$ ${formatarCentavos(anteriores.soma_centavos)}. Ir para as listas`"
                            @click="irParaAsListas"
                        >
                            <span aria-hidden="true" class="anteriores-seta">‹</span>
                            <span aria-hidden="true" class="anteriores-rotulo">Anteriores</span>
                            <span aria-hidden="true" class="anteriores-soma">{{ moedaCompacta.format(anteriores.soma_centavos / 100) }}</span>
                            <span aria-hidden="true" class="anteriores-numero">{{ anteriores.quantidade }}</span>
                        </button>
                        <span class="rotulo-faixa rotulo-pag">Pagamentos</span>
                    </div>

                    <div ref="grade" class="grade-dias">
                        <div class="sombras" aria-hidden="true">
                            <div v-for="dia in dias" :key="dia" class="sombra" :class="sombraDoDia(dia)" />
                        </div>
                        <div class="regua-caixa" aria-hidden="true">
                            <div v-for="linha in linhasDeReferencia" :key="linha.rotulo" class="grade-linha" :style="{ top: `${linha.topo}%` }"><span>{{ linha.rotulo }}</span></div>
                            <svg class="regua-svg regua-projecao" :viewBox="`0 0 ${regua.larguraTotal} 100`" preserveAspectRatio="none">
                                <path class="proj-linha" :d="regua.projecao" :style="{ d: `path('${regua.projecao}')` }" />
                            </svg>
                            <div class="regua-enche" :style="{ width: `${posicaoDoDia(hoje, dias) * 100}%` }">
                                <svg class="regua-svg" :viewBox="`0 0 ${regua.larguraRecebido} 100`" preserveAspectRatio="none">
                                    <path class="rec-area" :d="regua.area" :style="{ d: `path('${regua.area}')` }" />
                                    <path class="rec-linha" :d="regua.linha" :style="{ d: `path('${regua.linha}')` }" />
                                </svg>
                            </div>
                            <span ref="ponta" class="regua-ponta" :style="{ left: `${posicaoDoDia(hoje, dias) * 100}%`, top: `${regua.pontaY}%` }" />
                            <span class="regua-meta">{{ metaDoMes }}</span>
                        </div>
                        <div class="espinha" />

                        <div class="dias">
                            <div v-for="coluna in colunas" :key="coluna.dia" class="dia" :class="{ 'e-hoje': coluna.dia === hoje }" :style="{ '--i': coluna.dia - 1 }">
                                <div class="dia-regua" />
                                <div class="dia-venc">
                                    <button
                                        v-for="item in coluna.vencimentos"
                                        :key="item.ponto.id"
                                        type="button"
                                        class="pt"
                                        :class="[`pt-${tipo(item.ponto)}`, { aceso: destaque === item.ponto.id, sel: aberto?.ponto.id === item.ponto.id }]"
                                        :data-ponto="item.ponto.id"
                                        :data-etapa="item.ponto.etapa"
                                        :style="{ '--j': item.nivel }"
                                        :aria-label="rotuloDoPonto(item.ponto)"
                                        aria-haspopup="dialog"
                                        :aria-expanded="aberto?.ponto.id === item.ponto.id"
                                        @click="abrir(item.ponto, $event)"
                                        @mouseenter="entrouNoPonto(item.ponto, $event)"
                                        @mouseleave="saiuDoPonto"
                                        @focus="entrouNoPonto(item.ponto, $event)"
                                        @blur="saiuDoPonto"
                                        @keydown="teclaNoPonto($event, item.ponto)"
                                    >
                                        <span aria-hidden="true">{{ valorCompacto(valorDoPonto(item.ponto)) }}</span>
                                        <template v-if="tipo(item.ponto) === 'vencida'">
                                            <span class="pulso-pt" aria-hidden="true" />
                                            <span class="pt-alerta" aria-hidden="true">!</span>
                                        </template>
                                    </button>
                                    <button
                                        v-if="coluna.maisVencimentos"
                                        type="button"
                                        class="pt pt-mais"
                                        :style="{ '--j': NIVEIS_POR_DIA - 1 }"
                                        :aria-label="rotuloDoMais(coluna.maisVencimentos)"
                                        @click="irParaAsListas"
                                    >
                                        <span aria-hidden="true">{{ textoDoMais(coluna.maisVencimentos) }}</span>
                                    </button>
                                </div>
                                <div class="dia-eixo" :title="feriadoDo(coluna.dia) ? `${coluna.dia}, feriado: ${feriadoDo(coluna.dia)}` : undefined">
                                    <span class="dia-num">{{ coluna.dia }}</span>
                                    <span class="dia-sem">{{ semanaDoDia(coluna.dia) }}</span>
                                    <span class="dia-fer">{{ feriadoDo(coluna.dia) ? 'feriado' : '' }}</span>
                                </div>
                                <div class="dia-pag">
                                    <button
                                        v-for="item in coluna.pagamentos"
                                        :key="item.ponto.id"
                                        type="button"
                                        class="pt pt-recebida"
                                        :class="{ aceso: destaque === item.ponto.id, sel: aberto?.ponto.id === item.ponto.id }"
                                        :data-ponto="item.ponto.id"
                                        data-etapa="recebida"
                                        :style="{ '--j': item.nivel }"
                                        :aria-label="rotuloDoPonto(item.ponto)"
                                        aria-haspopup="dialog"
                                        :aria-expanded="aberto?.ponto.id === item.ponto.id"
                                        @click="abrir(item.ponto, $event)"
                                        @mouseenter="entrouNoPonto(item.ponto, $event)"
                                        @mouseleave="saiuDoPonto"
                                        @focus="entrouNoPonto(item.ponto, $event)"
                                        @blur="saiuDoPonto"
                                        @keydown="teclaNoPonto($event, item.ponto)"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 8.5l3.5 3.5 7.5-8" /></svg>
                                        <span class="rompe" aria-hidden="true"><i v-for="k in 10" :key="k" :style="{ '--a': `${(k - 1) * 36}deg` }" /><b /></span>
                                    </button>
                                    <button
                                        v-if="coluna.maisPagamentos"
                                        type="button"
                                        class="pt pt-mais"
                                        :style="{ '--j': NIVEIS_POR_DIA - 1 }"
                                        :aria-label="rotuloDoMais(coluna.maisPagamentos)"
                                        @click="irParaAsListas"
                                    >
                                        <span aria-hidden="true">{{ textoDoMais(coluna.maisPagamentos) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="hoje" aria-hidden="true" :style="{ left: `${posicaoDoDia(hoje, dias) * 100}%` }"><span>Hoje</span></div>
                        <svg ref="arcos" class="arcos" aria-hidden="true" />
                    </div>
                </div>
            </div>
            <p class="rodape-tempo">
                Vencimentos acima da linha, pagamentos abaixo. Sombreado é fim de semana<template v-if="feriadosDoMes">; hachurado é feriado ({{ feriadosDoMes }})</template>.
            </p>
        </template>

        <!-- No body: a animação de entrada da página (`.aparecer`) vira o bloco de contenção de um `fixed` que fique dentro dela. -->
        <Teleport to="body">
            <div v-if="dica" class="dica" role="tooltip" :style="dica.estilo">
                <b>{{ dica.ponto.cliente }}</b>
                <span class="dica-valor">R$ {{ formatarCentavos(valorDoPonto(dica.ponto)) }}</span>
                <span>{{ textoDoPonto(dica.ponto) }}</span>
            </div>
        </Teleport>

        <PopoverDaCobranca
            v-if="aberto"
            :key="aberto.ponto.id"
            :id="aberto.ponto.id"
            :ancora="aberto.ancora"
            :descricao="textoDoPonto(aberto.ponto)"
            :chaves-pix="chavesPix"
            :formas-pagamento="formasPagamento"
            :modelos="modelos"
            :prestador="prestador"
            @fechar="fecharPopover"
        />
    </div>
</template>

<style scoped>
/*
 * Linha do tempo. Estilos aqui (não em utilidades) porque a grade, a hachura e as animações precisam de pseudo-elementos
 * e keyframes; as cores vêm dos tokens do tema (`--color-*`), com reserva para o caso de a variável não existir.
 */
.tempo {
    --dia: 38px; --pt-alt: 28px; --pt-folga: 5px; --topo: 34px; --alt-regua: 84px; --calha: 92px;
    --alt-venc: calc(var(--n-venc) * (var(--pt-alt) + var(--pt-folga)) + 16px);
    --y-espinha: calc(var(--topo) + var(--alt-regua) + 14px + var(--alt-venc));
    --tinta: var(--color-grafite);
    --apagado: var(--color-apagado, var(--color-grafite));
    --faixa: var(--color-faixa, var(--color-superficie));
    --recebido: var(--color-etapa-recebida);
    position: relative;
    display: grid;
    grid-template-columns: var(--calha) minmax(0, 1fr);
    min-width: calc(var(--calha) + var(--dias) * var(--dia));
    font-variant-numeric: tabular-nums;
}
.rolagem { overflow-x: auto; overflow-y: hidden; overscroll-behavior-x: contain; scrollbar-width: thin; padding-bottom: 4px; }
.esqueleto { display: grid; place-items: center; min-height: 15rem; }
.falha { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-tinta-vermelha); background: var(--color-superficie); font-size: 14px; }
.falha-leve { margin-bottom: 8px; }

.calha { position: sticky; left: 0; z-index: 6; background: var(--faixa); border-right: 1px solid color-mix(in srgb, var(--tinta) 10%, transparent); }
.rotulo-faixa { position: absolute; left: 8px; font-size: 12px; font-weight: 700; color: var(--apagado); }
.rotulo-venc { top: calc(var(--y-espinha) - 26px); }
.rotulo-pag { top: calc(var(--y-espinha) + 64px + 6px); }
.anteriores { position: absolute; left: 6px; right: 6px; top: calc(var(--y-espinha) + 8px); display: flex; flex-direction: column; align-items: flex-start; padding: 4px 6px; border: 1px solid var(--color-tinta-vermelha); border-radius: 8px; background: var(--color-superficie); color: var(--color-tinta-vermelha); font-size: 11.5px; line-height: 1.25; text-align: left; cursor: pointer; transition: background-color .15s; }
.anteriores:hover { background: color-mix(in srgb, var(--color-tinta-vermelha) 8%, var(--color-superficie)); }
.anteriores-seta { display: none; font-size: 18px; font-weight: 700; line-height: 1; }
.anteriores-rotulo { font-weight: 700; }
.anteriores-soma { font-weight: 600; white-space: nowrap; }
.anteriores-numero { position: absolute; top: -9px; right: -7px; min-width: 18px; height: 18px; padding: 0 4px; display: grid; place-items: center; border-radius: 9px; background: var(--color-tinta-vermelha); color: #fff; font-size: 11px; font-weight: 800; }

.grade-dias { position: relative; }
.sombras { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(var(--dias), minmax(0, 1fr)); }
.sombra { border-left: 1px solid color-mix(in srgb, var(--tinta) 7%, transparent); }
.sombra:last-child { border-right: 1px solid color-mix(in srgb, var(--tinta) 7%, transparent); }
.sombra.fim-de-semana { background: var(--color-fim-de-semana, transparent); }
.sombra.feriado { background-color: var(--color-feriado, transparent); background-image: repeating-linear-gradient(135deg, transparent 0 6px, color-mix(in srgb, var(--tinta) 14%, transparent) 6px 7.5px); }

.regua-caixa { position: absolute; left: 0; right: 0; top: var(--topo); height: var(--alt-regua); z-index: 1; }
.regua-caixa::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 7px; border-bottom: 1.5px solid color-mix(in srgb, var(--tinta) 45%, transparent); background: repeating-linear-gradient(90deg, color-mix(in srgb, var(--tinta) 45%, transparent) 0 1px, transparent 1px calc(100% / var(--dias))); }
.grade-linha { position: absolute; left: 0; right: 0; height: 0; border-top: 1px dashed color-mix(in srgb, var(--tinta) 22%, transparent); }
.grade-linha span { position: absolute; left: 6px; top: -16px; font-size: 11.5px; font-weight: 600; color: var(--apagado); }
.regua-svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.regua-enche { position: absolute; left: 0; top: 0; height: 100%; transition: width .9s cubic-bezier(.3,.7,.3,1); }
.rec-area { fill: color-mix(in srgb, var(--recebido) 30%, transparent); transition: d .9s cubic-bezier(.3,.7,.3,1); }
.rec-linha { fill: none; stroke: var(--recebido); stroke-width: 3; stroke-linejoin: round; vector-effect: non-scaling-stroke; transition: d .9s cubic-bezier(.3,.7,.3,1); }
.proj-linha { fill: none; stroke: var(--apagado); stroke-width: 2.5; stroke-dasharray: 1 7; stroke-linecap: round; vector-effect: non-scaling-stroke; transition: d .9s cubic-bezier(.3,.7,.3,1); }
.regua-meta { position: absolute; right: 10px; top: -4px; transform: translateY(-100%); font: 700 13px/1 var(--font-titulo); color: var(--apagado); white-space: nowrap; }
.regua-ponta { position: absolute; width: 16px; height: 16px; border-radius: 50%; background: var(--recebido); border: 3px solid var(--color-superficie); transform: translate(-50%, -50%); z-index: 2; transition: top .9s cubic-bezier(.3,.7,.3,1); }
.regua-ponta.pisca { animation: ponta 1.1s ease-out; }
@keyframes ponta { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--recebido) 70%, transparent); } 100% { box-shadow: 0 0 0 22px transparent; } }

.espinha { position: absolute; left: 0; right: 0; top: var(--y-espinha); height: 3px; margin-top: -1px; border-radius: 3px; background: var(--tinta); transform-origin: left; z-index: 2; }
.hoje { position: absolute; top: 0; height: var(--y-espinha); width: 2px; margin-left: -1px; background: var(--tinta); transform-origin: top; z-index: 2; pointer-events: none; }
.hoje span { position: absolute; top: 6px; left: 50%; transform: translateX(-50%); padding: 4px 11px; border-radius: 999px; background: var(--tinta); color: var(--color-superficie); font: 700 14px/1 var(--font-titulo); }

.dias { position: relative; z-index: 3; display: grid; grid-template-columns: repeat(var(--dias), minmax(0, 1fr)); }
.dia { display: flex; flex-direction: column; align-items: stretch; min-width: 0; }
.dia-regua { height: calc(var(--topo) + var(--alt-regua) + 14px); }
.dia-venc { display: flex; flex-direction: column-reverse; align-items: center; justify-content: flex-start; gap: var(--pt-folga); height: var(--alt-venc); padding-bottom: 10px; }
.dia-eixo { position: relative; height: 64px; display: flex; flex-direction: column; align-items: center; padding-top: 12px; }
.dia-eixo::before { content: ''; position: absolute; top: 0; left: 50%; width: 2px; height: 9px; margin-left: -1px; background: var(--tinta); opacity: .6; }
.dia-num { min-width: 26px; height: 26px; padding: 0 4px; display: grid; place-items: center; border-radius: 999px; font: 700 17px/1 var(--font-numeros); }
.dia.e-hoje .dia-num { background: var(--tinta); color: var(--color-superficie); }
.dia-sem { margin-top: 1px; font-size: 12px; line-height: 14px; color: var(--apagado); }
.dia-fer { font-size: 11px; font-weight: 700; line-height: 13px; color: var(--tinta); }
.dia-pag { display: flex; flex-direction: column; align-items: center; gap: var(--pt-folga); padding-top: 10px; min-height: calc(var(--n-pag) * (var(--pt-alt) + var(--pt-folga)) + 14px); }
.rodape-tempo { margin: 8px 0 0; font-size: 13.5px; color: var(--apagado); }

/* Os pontos: um botão por cobrança, na cor da etapa */
.pt { position: relative; z-index: 1; flex: none; display: grid; place-items: center; width: calc(100% - 4px); max-width: 56px; height: var(--pt-alt); padding: 0; border-radius: 999px; border: 2px solid transparent; background: var(--tinta); color: var(--color-sobre-acao); font: 700 12.5px/1 var(--font-numeros); cursor: pointer; transition: transform .15s, box-shadow .15s, opacity .25s, filter .25s; }
.pt-enviar { background: var(--color-superficie); border-color: var(--color-etapa-enviar); color: var(--color-etapa-enviar); }
.pt-aguardando { background: var(--color-etapa-aguardando); }
.pt-hoje { background: var(--color-tinta-laranja); color: #fff; }
.pt-vencida { background: var(--color-tinta-vermelha); color: #fff; }
.pt-recebida { background: var(--recebido); width: var(--pt-alt); }
/* "+N": o que passou do teto da pilha do dia; as listas abaixo mostram tudo. Tracejado = não é uma cobrança só. */
.pt-mais { background: var(--color-superficie); border: 2px dashed var(--tinta); color: var(--tinta); font-size: 12px; }
.pt:hover, .pt.aceso { transform: scale(1.12); z-index: 5; }
.pt.aceso { box-shadow: 0 0 0 2px var(--color-superficie), 0 0 0 4.5px var(--color-carbono); }
.pt.sel { box-shadow: 0 0 0 2px var(--color-superficie), 0 0 0 4.5px var(--tinta); z-index: 5; }
.pt:focus-visible { outline: 3px solid var(--tinta); outline-offset: 3px; z-index: 6; }
.pt svg { width: 14px; height: 14px; }
.pt-alerta { position: absolute; top: -7px; right: -4px; width: 15px; height: 15px; border-radius: 50%; display: grid; place-items: center; background: var(--color-superficie); border: 2px solid var(--color-tinta-vermelha); color: var(--color-tinta-vermelha); font: 800 11px/1 var(--font-sans); }
.pulso-pt { position: absolute; inset: -2px; border-radius: inherit; border: 2px solid var(--color-tinta-vermelha); opacity: 0; pointer-events: none; animation: pulso-pt 1.6s ease-out 1.9s 4; }
@keyframes pulso-pt { 0% { opacity: .8; transform: scale(1); } 100% { opacity: 0; transform: scale(1.7, 1.9); } }
.tempo[data-filtro='enviar'] .pt:not([data-etapa='enviar']),
.tempo[data-filtro='aguardando'] .pt:not([data-etapa='aguardando']),
.tempo[data-filtro='recebida'] .pt:not([data-etapa='recebida']) { opacity: .2; filter: grayscale(.8); }

/* Baixa: rompante de pago e o traço do arco do salto */
.rompe { position: absolute; left: 50%; top: 50%; width: 0; height: 0; pointer-events: none; }
.rompe i, .rompe b { display: none; }
.pt-novo .rompe i { display: block; position: absolute; width: 6px; height: 6px; margin: -3px; border-radius: 50%; background: var(--color-ouro); animation: particula .85s cubic-bezier(.2,.7,.3,1) .68s both; }
.pt-novo .rompe i:nth-child(even) { background: var(--recebido); }
.pt-novo .rompe b { display: block; position: absolute; width: 26px; height: 26px; margin: -13px; border-radius: 50%; border: 3px solid var(--recebido); animation: anel .85s ease-out .68s both; }
@keyframes particula { 0% { opacity: 0; transform: rotate(var(--a)) translateX(6px); } 12% { opacity: 1; } 100% { opacity: 0; transform: rotate(var(--a)) translateX(42px) scale(.4); } }
@keyframes anel { 0% { opacity: 0; transform: scale(.6); } 12% { opacity: .9; } 100% { opacity: 0; transform: scale(3.2); } }
.pt-novo svg path { stroke-dasharray: 20; animation: tracar-certo .4s ease-out .62s backwards; }
@keyframes tracar-certo { from { stroke-dashoffset: 20; } }
.arcos { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none; z-index: 7; }
:deep(.arco) { fill: none; stroke: var(--recebido); stroke-width: 3; stroke-linecap: round; opacity: .6; stroke-dasharray: 1; }

/* Abertura: o único momento orquestrado (a linha se desenha e os pontos caem no lugar) */
.tempo.abrindo .espinha { animation: tracar-linha 1s cubic-bezier(.65,0,.25,1) backwards; }
@keyframes tracar-linha { from { transform: scaleX(0); } }
.tempo.abrindo .dia-eixo { animation: surgir-eixo .35s ease-out backwards; animation-delay: calc(100ms + var(--i) * 28ms); }
@keyframes surgir-eixo { from { opacity: 0; } }
.tempo.abrindo .dia-venc .pt { animation: cair .7s cubic-bezier(.3,1.4,.5,1) backwards; animation-delay: calc(850ms + var(--i) * 26ms + var(--j) * 90ms); }
.tempo.abrindo .dia-pag .pt { animation: brotar-pt .7s cubic-bezier(.3,1.4,.5,1) backwards; animation-delay: calc(850ms + var(--i) * 26ms + var(--j) * 90ms); }
@keyframes cair { from { opacity: 0; transform: translateY(-40px) scale(.5); } }
@keyframes brotar-pt { from { opacity: 0; transform: scale(.2); } }
.tempo.abrindo .regua-enche { animation: encher 1.25s cubic-bezier(.5,0,.2,1) .55s backwards; }
@keyframes encher { from { clip-path: inset(-12px 100% -12px 0); } to { clip-path: inset(-12px 0 -12px 0); } }
.tempo.abrindo .regua-svg.regua-projecao, .tempo.abrindo .grade-linha, .tempo.abrindo .regua-meta { animation: surgir-eixo .7s ease-out 1.5s backwards; }
.tempo.abrindo .regua-ponta { animation: brotar .5s cubic-bezier(.3,1.6,.5,1) 1.75s backwards; }
@keyframes brotar { from { opacity: 0; transform: translate(-50%, -50%) scale(.2); } }
.tempo.abrindo .hoje { animation: descer .7s cubic-bezier(.3,.8,.3,1) 1.3s backwards; }
@keyframes descer { from { transform: scaleY(0); } }

/* Dica ao passar o mouse ou focar */
.dica { position: fixed; z-index: 20; display: flex; flex-direction: column; gap: 2px; padding: 10px 14px; border-radius: 8px; background: var(--color-grafite); color: var(--color-superficie); font-size: 13px; line-height: 1.4; box-shadow: 0 12px 28px color-mix(in srgb, var(--color-grafite) 30%, transparent); pointer-events: none; }
.dica b { font: 700 15px/1.2 var(--font-titulo); }
.dica .dica-valor { font: 800 20px/1.1 var(--font-numeros); }

@media (max-width: 700px) {
    .tempo { --dia: 54px; --pt-alt: 32px; --pt-folga: 12px; --calha: 52px; }
    .tempo.sem-anteriores { --calha: 0px; }
    .tempo.sem-anteriores .calha { display: none; }
    .rotulo-faixa, .anteriores-rotulo, .anteriores-soma { display: none; }
    .anteriores { flex-direction: row; align-items: center; justify-content: center; gap: 2px; padding: 6px 4px; }
    .anteriores-seta { display: inline; }
    .anteriores-numero { position: static; min-width: 0; height: auto; padding: 0; background: none; color: inherit; font-size: 15px; }
    .pt { width: calc(100% - 8px); }
    .pt::before { content: ''; position: absolute; inset: -6px -2px; }
    .pt svg { width: 16px; height: 16px; }
}

/* A regra global só zera a duração; com atraso e `backwards` o elemento ficaria escondido. Aqui desliga tudo. */
@media (prefers-reduced-motion: reduce) {
    .tempo *, .tempo *::before, .tempo *::after { animation: none !important; transition: none !important; }
}
</style>
