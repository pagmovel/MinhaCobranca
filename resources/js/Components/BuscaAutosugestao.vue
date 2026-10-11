<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [Number, String], default: '' },
    inicial: { type: Object, default: null },
    obrigatorio: { type: Boolean, default: false },
    desativado: { type: Boolean, default: false },
    vazio: { type: String, default: 'Digite parte do nome ou do CPF/CNPJ' },
    url: { type: String, default: '/clientes/busca' },
    nenhum: { type: String, default: 'Nenhum cliente encontrado.' },
    limparAoEscolher: { type: Boolean, default: false },
    /** Rótulo da opção de cadastro rápido ("Cadastrar cliente"). Vazio = sem a opção. */
    cadastrar: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'escolhido', 'cadastrar']);

const texto = ref(props.inicial?.nome ?? '');
const sugestoes = ref([]);
const ativo = ref(-1);
const aberto = ref(false);
const carregando = ref(false);
let espera = null;
let controle = null;

/** A opção de cadastro rápido entra como a última da lista: setas, Enter e clique funcionam igual às outras. */
const opcoes = computed(() => (props.cadastrar ? [...sugestoes.value, { cadastrar: true }] : sugestoes.value));
const rotuloCadastro = computed(() => (texto.value.trim() ? `${props.cadastrar} «${texto.value.trim()}»` : `${props.cadastrar} novo`));

async function buscar(termo) {
    controle?.abort();
    const limpo = termo.trim();
    if (limpo.length === 1) {
        sugestoes.value = [];
        carregando.value = false;
        aberto.value = Boolean(props.cadastrar);
        ativo.value = props.cadastrar ? 0 : -1;
        return;
    }
    controle = new AbortController();
    const meu = controle;
    carregando.value = true;
    try {
        const resposta = await fetch(`${props.url}?q=${encodeURIComponent(limpo)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: meu.signal,
        });
        sugestoes.value = resposta.ok ? await resposta.json() : [];
        ativo.value = opcoes.value.length ? 0 : -1;
        aberto.value = true;
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            sugestoes.value = [];
        }
    } finally {
        if (controle === meu) {
            carregando.value = false;
        }
    }
}

function digitou() {
    emit('update:modelValue', ''); // o texto mudou: a escolha anterior deixa de valer
    clearTimeout(espera);
    espera = setTimeout(() => buscar(texto.value), 250);
}

/** Campo vazio recebendo o foco: mostra os primeiros, sem precisar adivinhar o que digitar. */
function focou() {
    if (!props.desativado && texto.value.trim() === '' && !aberto.value) {
        buscar('');
    }
}

function escolher(opcao) {
    aberto.value = false;
    if (opcao.cadastrar) {
        emit('cadastrar', texto.value.trim());
        return;
    }
    texto.value = props.limparAoEscolher ? '' : opcao.nome;
    emit('update:modelValue', opcao.id);
    emit('escolhido', opcao);
}

function teclado(evento) {
    if (evento.key === 'ArrowDown' && !aberto.value) {
        evento.preventDefault();
        buscar(texto.value);
        return;
    }
    if (!aberto.value || !opcoes.value.length) {
        return;
    }
    const total = opcoes.value.length;
    if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        ativo.value = (ativo.value + 1) % total;
    } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        ativo.value = (ativo.value - 1 + total) % total;
    } else if (evento.key === 'Enter') {
        evento.preventDefault();
        escolher(opcoes.value[ativo.value]);
    } else if (evento.key === 'Escape') {
        evento.stopPropagation();
        aberto.value = false;
    }
}

watch(() => props.inicial, (opcao) => {
    texto.value = opcao?.nome ?? '';
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
            class="entrada pr-9"
            :aria-expanded="aberto"
            :aria-controls="`${id}-lista`"
            :aria-activedescendant="aberto && ativo >= 0 ? `${id}-opcao-${ativo}` : undefined"
            :aria-busy="carregando"
            :placeholder="vazio"
            :required="obrigatorio"
            :disabled="desativado"
            @input="digitou"
            @focus="focou"
            @click="focou"
            @keydown="teclado"
            @blur="aberto = false"
        >
        <Transition name="surgir">
            <span
                v-if="carregando"
                class="pointer-events-none absolute top-1/2 right-3 -mt-2 h-4 w-4 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono"
                aria-hidden="true"
            />
        </Transition>
        <span v-if="carregando" class="sr-only" role="status">Buscando…</span>
        <Transition name="surgir">
            <ul
                v-show="aberto"
                :id="`${id}-lista`"
                role="listbox"
                class="absolute z-20 mt-1 max-h-72 w-full overflow-auto border border-linha bg-white shadow-md"
            >
                <li v-if="!sugestoes.length && !carregando" class="px-3 py-2 text-sm text-grafite/70">{{ nenhum }}</li>
                <li
                    v-for="(opcao, indice) in opcoes"
                    :id="`${id}-opcao-${indice}`"
                    :key="opcao.cadastrar ? 'cadastrar' : opcao.id"
                    role="option"
                    :aria-selected="indice === ativo"
                    class="cursor-pointer px-3 py-2 text-sm transition-colors duration-100"
                    :class="[indice === ativo ? 'bg-papel text-carbono' : '', opcao.cadastrar ? 'border-t border-linha font-semibold text-carbono' : '']"
                    @mousedown.prevent="escolher(opcao)"
                    @mouseenter="ativo = indice"
                >
                    <template v-if="opcao.cadastrar">+ {{ rotuloCadastro }}</template>
                    <slot v-else name="opcao" :opcao="opcao">
                        <span class="font-semibold">{{ opcao.nome }}</span>
                        <span class="ml-2 font-estreita text-xs text-grafite/70">{{ opcao.documento }}</span>
                    </slot>
                </li>
            </ul>
        </Transition>
    </div>
</template>
