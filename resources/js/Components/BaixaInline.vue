<script setup>
import { ref, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { formatarCentavos } from '../itens.js';
import { TEXTO_INTEGRAL, usarDescontoDaBaixa } from '../usarDescontoDaBaixa.js';

// naFaixa: aberto na faixa abaixo da linha da tabela; ao fechar, avisa a tabela em vez de mostrar o botão.
// valorCentavos: valor da cobrança, para mostrar, calcular o valor pago e travar o desconto em 100%.
const props = defineProps({
    cobrancaId: { type: Number, required: true },
    valorCentavos: { type: Number, required: true },
    naFaixa: { type: Boolean, default: false },
});
const emit = defineEmits(['fechar']);

const idDesconto = useId();
const aberto = ref(props.naFaixa);
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
const form = useForm({ pago_em: hoje, desconto_tipo: 'percentual', desconto: '' });

const { emReais, valorPagoCentavos, descontoIntegral, aviso, anuncio, erroDoDesconto } = usarDescontoDaBaixa(form, () => props.valorCentavos);

const fechar = () => { aberto.value = false; emit('fechar'); };
const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/pagar`, { preserveScroll: true, onSuccess: fechar });
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Dar baixa</button>
    <form v-else class="flex min-w-0 flex-wrap items-end gap-x-3 gap-y-2" @submit.prevent="confirmar" @keydown.esc="fechar">
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
                    :aria-describedby="erroDoDesconto ? `${idDesconto}-erro` : undefined"
                >
                <select v-model="form.desconto_tipo" class="entrada w-auto" aria-label="Tipo do desconto: percentual ou reais">
                    <option value="percentual">%</option>
                    <option value="valor">R$</option>
                </select>
            </div>
        </div>
        <div class="flex flex-col font-estreita text-xs">
            Valor pago
            <span class="border border-transparent py-2 text-sm font-semibold tabular-nums text-grafite">
                R$ <span :key="valorPagoCentavos" class="destacar inline-block px-1">{{ formatarCentavos(valorPagoCentavos) }}</span>
            </span>
        </div>
        <label class="flex flex-col font-estreita text-xs">
            Data do pagamento
            <input v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
        </label>
        <button class="botao" :disabled="form.processing">Confirmar baixa</button>
        <button type="button" class="botao-secundario" @click="fechar">Voltar</button>
        <p v-if="erroDoDesconto" :id="`${idDesconto}-erro`" class="w-full text-xs text-tinta-vermelha">{{ erroDoDesconto }}</p>
        <p v-if="form.errors.pago_em" class="w-full text-xs text-tinta-vermelha">{{ form.errors.pago_em }}</p>
        <Transition name="surgir">
            <p v-if="aviso" aria-hidden="true" class="w-full text-xs text-tinta-laranja">{{ aviso }}</p>
        </Transition>
        <Transition name="surgir">
            <p v-if="descontoIntegral" aria-hidden="true" class="w-full text-xs font-semibold text-tinta-laranja">{{ TEXTO_INTEGRAL }}</p>
        </Transition>
        <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
        <p class="sr-only" aria-live="polite">{{ anuncio }}</p>
    </form>
</template>
