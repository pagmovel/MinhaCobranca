<script setup>
import { computed, nextTick, ref, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MAXIMO_EM_CENTAVOS } from '../condicoes.js';
import { formatarCentavos } from '../itens.js';
import { TEXTO_INTEGRAL, usarValoresDaBaixa } from '../usarValoresDaBaixa.js';
import CampoValorOuPercentual from './CampoValorOuPercentual.vue';

/**
 * Cobrança paga: corrigir a data, o desconto, a multa, os juros e os outros acréscimos do pagamento, ou desfazer a baixa, com
 * senha e motivo (fica na auditoria). A correção abre com o que está gravado, em R$, e trocar a data não recalcula nada: ali se
 * corrige o que aconteceu de fato. Por isso os juros são o total em R$ (o gravado é o total), não "ao mês" como na baixa.
 */
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    enviada: { type: Boolean, default: false },
    // Na faixa abaixo da linha da tabela: já abre no modo escolhido no menu e avisa a tabela ao fechar.
    modoInicial: { type: String, default: null },
    // Valor da cobrança e o que a baixa gravou (desconto, multa, juros e outros): o formulário abre em R$ com esses valores,
    // para corrigir só a data sem mexer neles.
    valorCentavos: { type: Number, required: true },
    descontoCentavos: { type: Number, default: 0 },
    multaCentavos: { type: Number, default: 0 },
    jurosCentavos: { type: Number, default: 0 },
    outrosCentavos: { type: Number, default: 0 },
    // Vencimento (ISO): os dias de atraso na data do pagamento dizem se multa e juros se aplicam ("outros" vale sempre).
    vencimentoIso: { type: String, required: true },
    // Data atual do pagamento (ISO): o formulário abre nela, para não mudar o mês sem querer.
    pagoEmIso: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const id = useId();
const modo = ref(props.modoInicial);
const hoje = new Date().toLocaleDateString('sv-SE');
const emReais = (centavos) => (centavos > 0 ? formatarCentavos(centavos) : '');
// Os quatro valores abrem em R$ com o gravado; juros_tipo fica 'valor' (o campo é só em R$, sem seletor).
const form = useForm({
    pago_em: props.pagoEmIso,
    multa_tipo: 'valor',
    multa: emReais(props.multaCentavos),
    juros_tipo: 'valor',
    juros: emReais(props.jurosCentavos),
    outros: emReais(props.outrosCentavos),
    desconto_tipo: 'valor',
    desconto: emReais(props.descontoCentavos),
    motivo: '',
    senha: '',
});
const { dias, valorPagoCentavos, calculados, descontoIntegral, anuncio, erroDo, limparErro } = usarValoresDaBaixa(form, {
    valorCentavos: () => props.valorCentavos,
    vencimentoIso: () => props.vencimentoIso,
    regras: () => null,
    jurosTotais: true,
});
const primeiroCampo = ref(null);

const TEXTO_SEM_ATRASO = 'Nessa data não há atraso: multa e juros ficam zerados.';
// Sem atraso o servidor zera multa e juros, mesmo preenchidos: o aviso aparece quando isso muda alguma coisa. "Outros" vale em qualquer data.
const temMultaOuJuros = computed(() => [form.multa, form.juros].some((texto) => texto.trim() !== ''));
const semAtrasoComMultaOuJuros = computed(() => dias.value === 0 && temMultaOuJuros.value);
const anunciar = computed(() => (semAtrasoComMultaOuJuros.value ? TEXTO_SEM_ATRASO : anuncio.value));

const lancados = computed(() => [['desconto', props.descontoCentavos], ['multa', props.multaCentavos], ['juros', props.jurosCentavos], ['outros', props.outrosCentavos]]
    .filter(([, centavos]) => centavos > 0)
    .map(([nome, centavos]) => `${nome} de R$ ${formatarCentavos(centavos)}`));

const titulo = computed(() => {
    if (modo.value === 'corrigir') {
        return 'Corrigir a data e os valores do pagamento';
    }

    return `Desfazer a baixa: a cobrança volta a ficar em aberto${lancados.value.length > 0 ? ` e os valores lançados (${lancados.value.join(', ')}) são zerados` : ''}`;
});

const abrir = async (qual) => {
    form.reset();
    form.clearErrors();
    modo.value = qual;
    await nextTick();
    primeiroCampo.value?.focus();
};
const fechar = () => { modo.value = null; emit('fechar'); };

const confirmar = () => form.post(
    `/cobrancas/${props.cobrancaId}/${modo.value === 'corrigir' ? 'corrigir-pagamento' : 'desfazer-baixa'}`,
    {
        preserveScroll: true,
        onSuccess: fechar,
        onFinish: () => form.reset('senha'),
    },
);
</script>

<template>
    <span v-if="!modo" class="inline-flex flex-wrap gap-2">
        <button type="button" class="botao-secundario" @click="abrir('corrigir')">Corrigir pagamento</button>
        <button type="button" class="botao-perigo" @click="abrir('desfazer')">Desfazer baixa</button>
    </span>
    <form v-else class="aparecer grid w-full gap-2 border border-carbono/20 bg-papel p-3 sm:grid-cols-2" @submit.prevent="confirmar" @keydown.esc="fechar">
        <p class="text-sm font-medium sm:col-span-2">{{ titulo }}</p>
        <!-- items-start: a ajuda e o erro ficam embaixo dos campos sem subir os outros campos da linha. -->
        <div v-if="modo === 'corrigir'" class="flex min-w-0 flex-wrap items-start gap-x-3 gap-y-2 sm:col-span-2">
            <div class="flex flex-col gap-1 font-estreita text-xs">
                <label :for="`${id}-data`">Data certa do pagamento</label>
                <input
                    :id="`${id}-data`"
                    ref="primeiroCampo"
                    v-model="form.pago_em"
                    type="date"
                    class="entrada"
                    :max="hoje"
                    required
                    :aria-describedby="dias > 0 ? `${id}-atraso` : undefined"
                >
                <p v-if="dias > 0" :id="`${id}-atraso`" class="w-0 min-w-full text-grafite/80">{{ dias }} {{ dias === 1 ? 'dia' : 'dias' }} de atraso</p>
                <p v-if="form.errors.pago_em" class="w-0 min-w-full text-tinta-vermelha">{{ form.errors.pago_em }}</p>
            </div>
            <div class="flex flex-col gap-1 font-estreita text-xs">
                Valor
                <span class="border border-transparent py-2 text-sm tabular-nums text-grafite">R$ {{ formatarCentavos(valorCentavos) }}</span>
            </div>
            <!-- Multa e juros só com atraso (sem atraso o servidor os zera); "Outros" e o desconto aparecem sempre. -->
            <template v-if="dias > 0">
                <CampoValorOuPercentual
                    :id="`${id}-multa`"
                    v-model="form.multa"
                    v-model:tipo="form.multa_tipo"
                    rotulo="Multa"
                    :erro="erroDo('multa')"
                    ajuda="Multa por atraso, cobrada uma vez. Digite % ou R$."
                    :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                    @digitar="limparErro('multa')"
                >
                    <template #calculado="{ id: idCalculado }">
                        <p v-if="form.multa_tipo === 'percentual' && calculados.multa > 0" :id="idCalculado" class="tabular-nums text-grafite">= R$ {{ formatarCentavos(calculados.multa) }}</p>
                    </template>
                </CampoValorOuPercentual>
                <CampoValorOuPercentual
                    :id="`${id}-juros`"
                    v-model="form.juros"
                    rotulo="Juros (total)"
                    :erro="erroDo('juros')"
                    ajuda="Total dos juros cobrados, só em R$ (não é ao mês)."
                    :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                    somente-reais
                    @digitar="limparErro('juros')"
                />
            </template>
            <CampoValorOuPercentual
                :id="`${id}-outros`"
                v-model="form.outros"
                rotulo="Outros acréscimos"
                :erro="erroDo('outros')"
                ajuda="Taxas ou valores à parte, em R$. Vale em qualquer data."
                :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                somente-reais
                @digitar="limparErro('outros')"
            />
            <CampoValorOuPercentual
                :id="`${id}-desconto`"
                v-model="form.desconto"
                v-model:tipo="form.desconto_tipo"
                rotulo="Desconto"
                :erro="erroDo('desconto')"
                ajuda="Escolha % ou R$ e digite o desconto."
                :valor-maximo-centavos="valorCentavos"
                @digitar="limparErro('desconto')"
            >
                <template #calculado="{ id: idCalculado }">
                    <p v-if="form.desconto_tipo === 'percentual' && calculados.desconto > 0" :id="idCalculado" class="tabular-nums text-grafite">= R$ {{ formatarCentavos(calculados.desconto) }}</p>
                </template>
            </CampoValorOuPercentual>
            <div class="flex flex-col gap-1 font-estreita text-xs">
                Valor pago
                <span class="border border-transparent py-2 text-sm font-semibold tabular-nums text-grafite">
                    R$ <span :key="valorPagoCentavos" class="destacar inline-block px-1">{{ formatarCentavos(valorPagoCentavos) }}</span>
                </span>
            </div>
            <Transition name="surgir">
                <p v-if="semAtrasoComMultaOuJuros" aria-hidden="true" class="w-full text-xs text-tinta-laranja">{{ TEXTO_SEM_ATRASO }}</p>
            </Transition>
            <Transition name="surgir">
                <p v-if="descontoIntegral" aria-hidden="true" class="w-full text-xs font-semibold text-tinta-laranja">{{ TEXTO_INTEGRAL }}</p>
            </Transition>
            <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
            <p class="sr-only" aria-live="polite">{{ anunciar }}</p>
        </div>
        <label class="flex flex-col font-estreita text-xs" :class="{ 'sm:col-span-2': modo === 'desfazer' }">
            Motivo
            <input
                :ref="modo === 'desfazer' ? 'primeiroCampo' : undefined"
                v-model="form.motivo"
                type="text"
                maxlength="500"
                class="entrada"
                placeholder="Ex.: data digitada errada"
                required
            >
            <span v-if="form.errors.motivo" class="text-tinta-vermelha">{{ form.errors.motivo }}</span>
        </label>
        <label class="flex flex-col font-estreita text-xs">
            Sua senha (confirma que foi você)
            <input v-model="form.senha" type="password" autocomplete="current-password" class="entrada" required>
            <span v-if="form.errors.senha" class="text-tinta-vermelha">{{ form.errors.senha }}</span>
        </label>
        <p v-if="enviada && modo === 'corrigir'" class="text-xs text-grafite/80 sm:col-span-2">
            Se o recibo pago já foi enviado ao cliente, ele está com os dados antigos: envie de novo depois de corrigir.
        </p>
        <div class="flex flex-wrap gap-2 sm:col-span-2">
            <button :class="modo === 'corrigir' ? 'botao' : 'botao-perigo-confirmar'" :disabled="form.processing">
                {{ modo === 'corrigir' ? 'Corrigir pagamento' : 'Desfazer a baixa' }}
            </button>
            <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        </div>
    </form>
</template>
