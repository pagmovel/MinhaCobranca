<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [Number, String], default: '' },
    inicial: { type: Object, default: null },
    obrigatorio: { type: Boolean, default: false },
    desativado: { type: Boolean, default: false },
    vazio: { type: String, default: 'Digite parte do nome ou do CPF/CNPJ' },
});

const emit = defineEmits(['update:modelValue']);

const texto = ref(props.inicial?.nome ?? '');
const sugestoes = ref([]);
const ativo = ref(-1);
const aberto = ref(false);
let espera = null;
let controle = null;

async function buscar(termo) {
    controle?.abort();
    if (termo.trim().length < 2) {
        sugestoes.value = [];
        aberto.value = false;
        return;
    }
    controle = new AbortController();
    try {
        const resposta = await fetch(`/clientes/busca?q=${encodeURIComponent(termo)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controle.signal,
        });
        sugestoes.value = resposta.ok ? await resposta.json() : [];
        ativo.value = sugestoes.value.length ? 0 : -1;
        aberto.value = true;
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            sugestoes.value = [];
        }
    }
}

function digitou() {
    emit('update:modelValue', ''); // o texto mudou: a escolha anterior deixa de valer
    clearTimeout(espera);
    espera = setTimeout(() => buscar(texto.value), 250);
}

function escolher(cliente) {
    texto.value = cliente.nome;
    emit('update:modelValue', cliente.id);
    aberto.value = false;
}

function teclado(evento) {
    if (!aberto.value || !sugestoes.value.length) {
        return;
    }
    const total = sugestoes.value.length;
    if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        ativo.value = (ativo.value + 1) % total;
    } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        ativo.value = (ativo.value - 1 + total) % total;
    } else if (evento.key === 'Enter') {
        evento.preventDefault();
        escolher(sugestoes.value[ativo.value]);
    } else if (evento.key === 'Escape') {
        aberto.value = false;
    }
}

watch(() => props.inicial, (cliente) => {
    texto.value = cliente?.nome ?? '';
});

onBeforeUnmount(() => {
    clearTimeout(espera);
    controle?.abort();
});
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="texto"
            type="text"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            class="entrada"
            :aria-expanded="aberto"
            :aria-controls="`${id}-lista`"
            :aria-activedescendant="aberto && ativo >= 0 ? `${id}-opcao-${ativo}` : undefined"
            :placeholder="vazio"
            :required="obrigatorio"
            :disabled="desativado"
            @input="digitou"
            @keydown="teclado"
            @blur="aberto = false"
        >
        <ul
            v-show="aberto"
            :id="`${id}-lista`"
            role="listbox"
            class="absolute z-10 mt-1 max-h-72 w-full overflow-auto border border-linha bg-white"
        >
            <li
                v-for="(cliente, indice) in sugestoes"
                :id="`${id}-opcao-${indice}`"
                :key="cliente.id"
                role="option"
                :aria-selected="indice === ativo"
                class="cursor-pointer px-3 py-2 text-sm"
                :class="indice === ativo ? 'bg-papel text-carbono' : ''"
                @mousedown.prevent="escolher(cliente)"
                @mouseenter="ativo = indice"
            >
                <span class="font-semibold">{{ cliente.nome }}</span>
                <span class="ml-2 font-estreita text-xs text-grafite/70">{{ cliente.documento }}</span>
            </li>
            <li v-if="!sugestoes.length" class="px-3 py-2 text-sm text-grafite/70">Nenhum cliente encontrado.</li>
        </ul>
    </div>
</template>
