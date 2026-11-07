<script setup>
import { computed, provide, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { avisosDoPainel } from '../../avisosDoPainel.js';
import { frasePeloDia, somasPorEtapa } from '../../linhaDoTempo.js';
import { textosDoPainel, vazioDeEnviar } from '../../textosDoPainel.js';
import { usarMesDoPainel } from '../../usarMesDoPainel.js';
import BlocoVencendo from '../BlocoVencendo.vue';
import LinhaDoTempoDoMes from '../LinhaDoTempoDoMes.vue';
import ListaDoPainel from '../ListaDoPainel.vue';
import AcoesDaCobranca from './AcoesDaCobranca.vue';
import NumeroQueConta from './NumeroQueConta.vue';

// Composição da Régua: a faixa do mês (avisos, números e linha do tempo) e, embaixo, as listas reais. Mesmas props da
// página Painel.vue (toda composição declara as 11, senão a que sobra cai em $attrs).
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

const { dados, estado, falha, carregar } = usarMesDoPainel();
const somas = computed(() => (dados.value ? somasPorEtapa(dados.value.pontos) : { recebido: 0, aguardando: 0, enviar: 0, previsto: 0 }));
const fraseDeHoje = computed(() => (dados.value ? frasePeloDia(dados.value) : ''));
const nomeDoMes = computed(() => props.mes.split(' ')[0].toLowerCase());

// Etapa destacada pelos números do mês (esmaece as outras na linha do tempo); clicar de novo desliga.
const filtro = ref(null);
const alternarFiltro = (etapa) => { filtro.value = filtro.value === etapa ? null : etapa; };

// Ponto da linha do tempo e linha das listas acendem juntos nos dois sentidos (a `LinhaCobranca` lê isto).
const destaque = ref(null);
provide('destaqueDaCobranca', {
    id: destaque,
    ligar: (id) => { destaque.value = id; },
    desligar: (id) => { if (destaque.value === id) { destaque.value = null; } },
});

const avisos = computed(() => avisosDoPainel(props));
</script>

<template>
    <section class="faixa-do-mes rounded-(--raio-bloco) border border-linha bg-faixa" aria-labelledby="titulo-mes-regua">
        <div class="flex flex-col gap-4 p-4 md:p-5">
            <ul v-if="avisos.length" class="flex flex-wrap gap-x-8 gap-y-1">
                <li v-for="aviso in avisos" :key="aviso.href">
                    <Link :href="aviso.href" class="aviso-do-topo inline-flex min-h-8 items-center gap-2.5 text-sm text-grafite no-underline">
                        <span>{{ aviso.texto }}</span>
                        <span class="font-bold whitespace-nowrap text-carbono underline underline-offset-4">{{ aviso.acao }}</span>
                    </Link>
                </li>
            </ul>

            <header class="flex flex-wrap items-end justify-between gap-x-10 gap-y-4">
                <div>
                    <h1 id="titulo-mes-regua" class="font-titulo text-[clamp(2.5rem,4.4vw,3.75rem)] leading-[0.9] font-extrabold tracking-tighter">{{ mes }}</h1>
                    <p class="mt-2 text-[15px] text-apagado">{{ fraseDeHoje }}</p>
                </div>
                <div class="numeros" role="group" aria-label="Valores do mês. Toque para destacar uma etapa na linha do tempo">
                    <button type="button" class="stat stat-grande" :aria-pressed="filtro === 'recebida'" title="Destacar na linha do tempo" @click="alternarFiltro('recebida')">
                        <span class="stat-rotulo"><i class="bolinha bolinha-recebida" aria-hidden="true" />Recebido até hoje</span>
                        <NumeroQueConta :centavos="somas.recebido" class="stat-valor" />
                    </button>
                    <button type="button" class="stat" :aria-pressed="filtro === 'aguardando'" title="Destacar na linha do tempo" @click="alternarFiltro('aguardando')">
                        <span class="stat-rotulo"><i class="bolinha bolinha-aguardando" aria-hidden="true" />Aguardando pagamento</span>
                        <NumeroQueConta :centavos="somas.aguardando" class="stat-valor" />
                    </button>
                    <button type="button" class="stat" :aria-pressed="filtro === 'enviar'" title="Destacar na linha do tempo" @click="alternarFiltro('enviar')">
                        <span class="stat-rotulo"><i class="bolinha bolinha-enviar" aria-hidden="true" />Recibos a enviar</span>
                        <NumeroQueConta :centavos="somas.enviar" class="stat-valor" />
                    </button>
                    <div class="stat stat-fixo">
                        <span class="stat-rotulo">Previsto em {{ nomeDoMes }}</span>
                        <NumeroQueConta :centavos="somas.previsto" class="stat-valor" />
                    </div>
                </div>
            </header>

            <LinhaDoTempoDoMes
                :dados="dados"
                :estado="estado"
                :falha="falha"
                :filtro="filtro"
                :destaque="destaque"
                :chaves-pix="chavesPix"
                :formas-pagamento="formasPagamento"
                :modelos="modelosMensagem"
                :prestador="prestadorNome"
                @destacar="destaque = $event"
                @tentar="carregar"
            />
        </div>
    </section>

    <!-- Cada lista carrega por conta própria (/painel/dados); depois de uma ação, as três recarregam. -->
    <div class="listas-da-regua grid items-start gap-x-12 lg:grid-cols-[minmax(0,1fr)_minmax(19rem,23rem)]">
        <div id="o-que-fazer" class="min-w-0">
            <BlocoVencendo :dias-aviso="diasAviso" :modelos="modelosMensagem" :prestador="prestadorNome" />

            <ListaDoPainel
                v-slot="{ cobranca }"
                lista="para_enviar"
                :titulo="textosDoPainel.enviar.titulo"
                :dica="textosDoPainel.enviar.dica"
                :vazio="vazioDeEnviar(diasAntecedencia)"
            >
                <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            </ListaDoPainel>

            <ListaDoPainel v-slot="{ cobranca }" lista="aguardando" :titulo="textosDoPainel.aguardando.titulo" :dica="textosDoPainel.aguardando.dica" :vazio="textosDoPainel.aguardando.vazio">
                <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            </ListaDoPainel>
        </div>

        <div class="min-w-0">
            <ListaDoPainel v-slot="{ cobranca }" lista="recebidas" titulo="Recebidas no mês" vazio="Nenhum pagamento registrado neste mês.">
                <AcoesDaCobranca :cobranca="cobranca" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            </ListaDoPainel>
        </div>
    </div>
</template>

<style scoped>
/* Os títulos das listas (de outro componente) usam a fonte de título do tema, sem mexer na lista do Talão. */
.listas-da-regua :deep(h2) {
    font-family: var(--font-titulo);
    font-size: 1.5rem;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -0.01em;
}
.listas-da-regua :deep(h2 span) {
    font-family: var(--font-sans);
    font-size: 0.9375rem;
    font-weight: 600;
    letter-spacing: 0;
    color: var(--color-apagado);
}
.aviso-do-topo::before {
    content: '';
    flex: none;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--color-ouro);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-ouro) 35%, transparent);
}

.numeros { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px 32px; }
.stat { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; margin: -6px -10px; padding: 6px 10px; border: 0; border-radius: var(--raio-bloco); background: none; text-align: left; cursor: pointer; transition: background-color .15s, box-shadow .15s; }
.stat:hover { background: color-mix(in srgb, var(--color-superficie) 70%, transparent); }
.stat[aria-pressed='true'] { background: var(--color-superficie); box-shadow: 0 0 0 2px var(--color-grafite); }
.stat-fixo { cursor: default; }
.stat-fixo:hover { background: none; }
.stat-rotulo { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: var(--color-apagado); }
.stat-valor { font: 700 clamp(22px, 2.6vw, 30px) / 1.1 var(--font-numeros); letter-spacing: -0.01em; }
.stat-grande .stat-valor { font-size: clamp(38px, 4.6vw, 60px); font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
.bolinha { display: inline-block; flex: none; width: 14px; height: 14px; border-radius: 50%; }
.bolinha-recebida { background: var(--color-etapa-recebida); }
.bolinha-aguardando { background: var(--color-etapa-aguardando); }
.bolinha-enviar { background: var(--color-superficie); border: 2px solid var(--color-etapa-enviar); }

@media (max-width: 700px) {
    .numeros { flex-direction: column; align-items: stretch; gap: 0; width: 100%; }
    .stat { flex-direction: row; align-items: baseline; justify-content: space-between; gap: 12px; margin: 0 -10px; }
    .stat-grande { flex-direction: column; align-items: flex-start; }
    .stat-rotulo { font-size: 14px; }
    .stat-valor { font-size: 20px; white-space: nowrap; }
    .stat-grande .stat-valor { font-size: 42px; }
}
@media (prefers-reduced-motion: reduce) {
    .stat { transition: none; }
}
</style>
