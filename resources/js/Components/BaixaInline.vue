<script setup>
import { ref, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MAXIMO_EM_CENTAVOS } from '../condicoes.js';
import { formatarCentavos } from '../itens.js';
import { TEXTO_INTEGRAL, camposDaRegra, paraOForm, usarValoresDaBaixa } from '../usarValoresDaBaixa.js';
import CampoValorOuPercentual from './CampoValorOuPercentual.vue';

// naFaixa: aberto na faixa abaixo da linha da tabela; ao fechar, avisa a tabela em vez de mostrar o botão.
// valorCentavos: valor da cobrança, para mostrar, calcular o valor pago e travar o desconto em 100%.
// vencimentoIso e regras (do contrato, ou null): preenchem multa, juros e desconto pela data do pagamento.
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    valorCentavos: { type: Number, required: true },
    vencimentoIso: { type: String, required: true },
    regras: { type: Object, default: null },
    naFaixa: { type: Boolean, default: false },
});
const emit = defineEmits(['fechar']);

const id = useId();
const aberto = ref(props.naFaixa);
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
const form = useForm({ pago_em: hoje, ...paraOForm(camposDaRegra(props.vencimentoIso, hoje, props.regras)), outros: '' });

const { dias, valorPagoCentavos, calculados, descontoIntegral, anuncio, diferenteDaRegra, recalcular, etiqueta, erroDo, limparErro } = usarValoresDaBaixa(form, {
    valorCentavos: () => props.valorCentavos,
    vencimentoIso: () => props.vencimentoIso,
    regras: () => props.regras,
});

const fechar = () => { aberto.value = false; emit('fechar'); };
const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/pagar`, { preserveScroll: true, onSuccess: fechar });
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Dar baixa</button>
    <form v-else class="flex min-w-0 flex-wrap items-start gap-x-3 gap-y-2" @submit.prevent="confirmar" @keydown.esc="fechar">
        <!-- items-start: a ajuda e o erro ficam embaixo dos campos sem subir os outros campos da linha. -->
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
                :etiqueta="etiqueta('multa')"
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
                v-model:tipo="form.juros_tipo"
                rotulo="Juros ao mês"
                :etiqueta="etiqueta('juros')"
                :erro="erroDo('juros')"
                ajuda="Juros simples, proporcionais aos dias de atraso. Digite % ou R$."
                :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                @digitar="limparErro('juros')"
            >
                <template #calculado="{ id: idCalculado }">
                    <p v-if="calculados.juros > 0" :id="idCalculado" class="tabular-nums text-grafite">= R$ {{ formatarCentavos(calculados.juros) }}</p>
                </template>
            </CampoValorOuPercentual>
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
            :etiqueta="etiqueta('desconto')"
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
        <div class="flex flex-col gap-1 font-estreita text-xs">
            <label :for="`${id}-data`">Data do pagamento</label>
            <input
                :id="`${id}-data`"
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
        <!-- mt-5 = a altura do rótulo (16 px) mais o gap-1 das colunas: os botões ficam na linha dos campos. -->
        <div class="mt-5 flex gap-3">
            <button class="botao" :disabled="form.processing">Confirmar baixa</button>
            <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        </div>
        <p v-if="diferenteDaRegra" class="w-full text-xs text-grafite/80">
            Valores diferentes do contrato. <button type="button" class="font-semibold text-carbono underline" @click="recalcular">Recalcular</button>
        </p>
        <Transition name="surgir">
            <p v-if="descontoIntegral" aria-hidden="true" class="w-full text-xs font-semibold text-tinta-laranja">{{ TEXTO_INTEGRAL }}</p>
        </Transition>
        <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
        <p class="sr-only" aria-live="polite">{{ anuncio }}</p>
    </form>
</template>
