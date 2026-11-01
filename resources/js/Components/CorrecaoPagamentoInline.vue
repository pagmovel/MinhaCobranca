<script setup>
import { computed, nextTick, ref, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { semSeparadorFinal } from '../desconto.js';
import { formatarCentavos } from '../itens.js';
import { TEXTO_INTEGRAL, usarDescontoDaBaixa } from '../usarDescontoDaBaixa.js';

/** Cobrança paga: corrigir a data e o desconto do pagamento ou desfazer a baixa, com senha e motivo (fica na auditoria). */
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    enviada: { type: Boolean, default: false },
    // Na faixa abaixo da linha da tabela: já abre no modo escolhido no menu e avisa a tabela ao fechar.
    modoInicial: { type: String, default: null },
    // Valor da cobrança e desconto atual: o formulário abre em R$ com o desconto de hoje, para corrigir só a data sem mexer nele.
    valorCentavos: { type: Number, required: true },
    descontoCentavos: { type: Number, default: 0 },
});
const emit = defineEmits(['fechar']);

const idDesconto = useId();
const modo = ref(props.modoInicial);
const hoje = new Date().toLocaleDateString('sv-SE');
const form = useForm({
    pago_em: hoje,
    desconto_tipo: 'valor',
    desconto: props.descontoCentavos > 0 ? formatarCentavos(props.descontoCentavos) : '',
    motivo: '',
    senha: '',
});
const { emReais, valorPagoCentavos, descontoIntegral, aviso, anuncio, erroDoDesconto } = usarDescontoDaBaixa(form, () => props.valorCentavos);
const primeiroCampo = ref(null);

const titulo = computed(() => {
    if (modo.value === 'corrigir') {
        return 'Corrigir a data e o desconto do pagamento';
    }

    return `Desfazer a baixa: a cobrança volta a ficar em aberto${props.descontoCentavos > 0 ? ` e o desconto de R$ ${formatarCentavos(props.descontoCentavos)} é desfeito` : ''}`;
});

const abrir = async (qual) => {
    form.reset();
    form.clearErrors();
    modo.value = qual;
    await nextTick();
    primeiroCampo.value?.focus();
};
const fechar = () => { modo.value = null; emit('fechar'); };

const confirmar = () => form.transform((dados) => ({ ...dados, desconto: semSeparadorFinal(dados.desconto) })).post(
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
        <!-- items-start: a ajuda e o erro ficam embaixo dos campos sem subir o campo de desconto em relação aos outros. -->
        <div v-if="modo === 'corrigir'" class="flex min-w-0 flex-wrap items-start gap-x-3 gap-y-2 sm:col-span-2">
            <label class="flex flex-col font-estreita text-xs">
                Data certa do pagamento
                <input ref="primeiroCampo" v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
                <span v-if="form.errors.pago_em" class="text-tinta-vermelha">{{ form.errors.pago_em }}</span>
            </label>
            <div class="flex flex-col font-estreita text-xs">
                Valor
                <span class="border border-transparent py-2 text-sm tabular-nums text-grafite">R$ {{ formatarCentavos(valorCentavos) }}</span>
            </div>
            <div class="flex flex-col font-estreita text-xs">
                <label :for="idDesconto">Desconto</label>
                <div class="flex gap-1">
                    <input
                        :id="idDesconto"
                        v-model="form.desconto"
                        v-mascara="emReais ? 'valor' : 'percentual'"
                        inputmode="decimal"
                        autocomplete="off"
                        :placeholder="emReais ? '0,00' : '0'"
                        class="entrada w-24"
                        :aria-invalid="erroDoDesconto ? 'true' : undefined"
                        :aria-describedby="erroDoDesconto ? `${idDesconto}-ajuda ${idDesconto}-erro` : `${idDesconto}-ajuda`"
                    >
                    <select v-model="form.desconto_tipo" class="entrada w-auto" aria-label="Tipo do desconto: percentual ou reais">
                        <option value="percentual">%</option>
                        <option value="valor">R$</option>
                    </select>
                </div>
                <!-- w-0 min-w-full: o texto quebra na largura do campo, sem alargar a coluna. -->
                <p :id="`${idDesconto}-ajuda`" class="mt-1 w-0 min-w-full text-xs text-grafite/80">Escolha % ou R$ e digite o desconto.</p>
                <p v-if="erroDoDesconto" :id="`${idDesconto}-erro`" class="mt-1 w-0 min-w-full text-xs text-tinta-vermelha">{{ erroDoDesconto }}</p>
            </div>
            <div class="flex flex-col font-estreita text-xs">
                Valor pago
                <span class="border border-transparent py-2 text-sm font-semibold tabular-nums text-grafite">
                    R$ <span :key="valorPagoCentavos" class="destacar inline-block px-1">{{ formatarCentavos(valorPagoCentavos) }}</span>
                </span>
            </div>
            <Transition name="surgir">
                <p v-if="aviso" aria-hidden="true" class="w-full text-xs text-tinta-laranja">{{ aviso }}</p>
            </Transition>
            <Transition name="surgir">
                <p v-if="descontoIntegral" aria-hidden="true" class="w-full text-xs font-semibold text-tinta-laranja">{{ TEXTO_INTEGRAL }}</p>
            </Transition>
            <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
            <p class="sr-only" aria-live="polite">{{ anuncio }}</p>
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
