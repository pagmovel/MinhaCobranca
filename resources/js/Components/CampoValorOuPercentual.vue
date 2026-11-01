<script setup>
import { computed, useSlots, watch } from 'vue';
import { criarAvisoTemporario } from '../avisoTemporario.js';
import { semSeparadorFinal, travarDesconto } from '../desconto.js';
import { formatarCentavos } from '../itens.js';
import Etiqueta from './Etiqueta.vue';

/**
 * Campo de número com seletor `% | R$` (desconto, multa, juros): máscara por tipo, ajuda e erro ligados por
 * `aria-describedby`, trava em 100% (e, com `valorMaximoCentavos`, no valor máximo em R$), e trocar o seletor limpa o
 * número (10% não pode virar R$ 0,10). `v-model` é o número digitado e `v-model:tipo`, `percentual` ou `valor`.
 * `somenteReais`: sem seletor, sempre em R$ (o `tipo` não é usado). `etiqueta`: marca pequena ao lado do rótulo
 * (ex.: "contrato", quando o valor veio da regra do contrato). `complementoDoMaximo`: texto depois de "Máximo: R$ X,XX"
 * (no lote, " (valor da menor cobrança)"). Evento `digitar`: a pessoa mexeu no número ou no tipo (o pai limpa o erro do
 * servidor, como o `clearErrors` do formulário). Slot `calculado` (recebe `id`): o valor calculado ao lado do campo, por
 * exemplo "= R$ 4,20"; o elemento do slot usa esse `id` para entrar no `aria-describedby`.
 */
const props = defineProps({
    id: { type: String, required: true },
    rotulo: { type: String, required: true },
    erro: { type: String, default: '' },
    ajuda: { type: String, default: '' },
    etiqueta: { type: String, default: '' },
    valorMaximoCentavos: { type: Number, default: undefined },
    complementoDoMaximo: { type: String, default: '' },
    somenteReais: { type: Boolean, default: false },
});
const emit = defineEmits(['digitar']);
const slots = useSlots();

const numero = defineModel({ type: String, default: '' });
const tipo = defineModel('tipo', { type: String, default: 'percentual' });

const emReais = computed(() => props.somenteReais || tipo.value === 'valor');
const descricao = computed(() => [
    props.etiqueta && `${props.id}-etiqueta`,
    slots.calculado && `${props.id}-calculado`,
    props.ajuda && `${props.id}-ajuda`,
    props.erro && `${props.id}-erro`,
].filter(Boolean).join(' ') || undefined);
const { aviso, mostrar, limpar } = criarAvisoTemporario();

// A trava roda num `watch` do número, não num `@input`: o `v-model` registra o ouvinte no `created` e a máscara no
// `mounted`, então um `@input` veria o texto ainda sem máscara.
watch(numero, (texto) => {
    const { entrada, travou } = travarDesconto(props.valorMaximoCentavos ?? Infinity, emReais.value ? 'valor' : 'percentual', texto);

    if (travou) {
        numero.value = entrada;
        mostrar(emReais.value ? `Máximo: R$ ${formatarCentavos(props.valorMaximoCentavos)}${props.complementoDoMaximo}` : 'Máximo: 100%');
    }
});

const trocarTipo = (novo) => {
    limpar();
    tipo.value = novo;
    numero.value = '';
    emit('digitar');
};
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1 font-estreita text-xs">
        <span class="flex items-center gap-2">
            <label :for="id" class="font-medium text-grafite/75">{{ rotulo }}</label>
            <!-- compacta: a marca cabe na altura do rótulo e não desalinha os campos vizinhos da mesma linha. -->
            <Etiqueta v-if="etiqueta" :id="`${id}-etiqueta`" :texto="etiqueta" tom="apagado" compacta />
        </span>
        <div class="flex gap-1">
            <input
                :id="id"
                v-model="numero"
                v-mascara="emReais ? 'valor' : 'percentual'"
                inputmode="decimal"
                autocomplete="off"
                :placeholder="emReais ? '0,00' : '0'"
                class="entrada w-24 min-w-0 grow"
                :aria-invalid="erro ? 'true' : undefined"
                :aria-describedby="descricao"
                @input="emit('digitar')"
                @change="numero = semSeparadorFinal(numero)"
            >
            <span v-if="somenteReais" class="py-2 text-sm text-grafite">R$</span>
            <select v-else :value="tipo" class="entrada w-auto" :aria-label="`Tipo de ${rotulo.toLowerCase()}: percentual ou reais`" @change="trocarTipo($event.target.value)">
                <option value="percentual">%</option>
                <option value="valor">R$</option>
            </select>
        </div>
        <slot name="calculado" :id="`${id}-calculado`" />
        <!-- w-0 min-w-full: o texto quebra na largura do campo, sem alargar a coluna. -->
        <p v-if="ajuda" :id="`${id}-ajuda`" class="w-0 min-w-full text-grafite/80">{{ ajuda }}</p>
        <p v-if="erro" :id="`${id}-erro`" class="w-0 min-w-full text-tinta-vermelha">{{ erro }}</p>
        <Transition name="surgir">
            <p v-if="aviso" aria-hidden="true" class="w-0 min-w-full text-tinta-laranja">{{ aviso }}</p>
        </Transition>
        <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
        <p class="sr-only" aria-live="polite">{{ aviso }}</p>
    </div>
</template>
