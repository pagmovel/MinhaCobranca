<script setup>
import { ref, watch } from 'vue';
import Modal from './Modal.vue';

/** Catálogo em lista: inclui vários itens sem fechar a janela; o que já está no contrato aparece marcado. */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    incluidos: { type: Array, required: true },
});

const emit = defineEmits(['fechar', 'incluir', 'cadastrar']);

const termo = ref('');
const itens = ref([]);
const carregando = ref(false);
let espera = null;
let controle = null;

async function buscar() {
    controle?.abort();
    const meu = (controle = new AbortController());
    carregando.value = true;
    try {
        const resposta = await fetch(`/produtos-servicos/busca?q=${encodeURIComponent(termo.value.trim().length === 1 ? '' : termo.value.trim())}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: meu.signal,
        });
        itens.value = resposta.ok ? await resposta.json() : [];
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            itens.value = [];
        }
    } finally {
        if (controle === meu) {
            carregando.value = false;
        }
    }
}

function digitou() {
    clearTimeout(espera);
    espera = setTimeout(buscar, 250);
}

watch(() => props.aberto, (aberto) => {
    if (aberto) {
        termo.value = '';
        buscar();
    }
});
</script>

<template>
    <Modal :aberto="aberto" titulo="Catálogo de produtos e serviços" largura="max-w-2xl" @fechar="emit('fechar')">
        <div class="relative mb-3">
            <label for="catalogo_busca" class="sr-only">Buscar no catálogo</label>
            <input
                id="catalogo_busca"
                v-model="termo"
                type="search"
                class="entrada pr-9"
                placeholder="Buscar pelo nome"
                autocomplete="off"
                autofocus
                @input="digitou"
            >
            <Transition name="surgir">
                <span v-if="carregando" class="pointer-events-none absolute top-1/2 right-3 -mt-2 h-4 w-4 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
            </Transition>
        </div>

        <p v-if="!itens.length && !carregando" class="border border-linha bg-papel px-4 py-3 text-sm">
            {{ termo.trim() ? 'Nada encontrado com esse nome.' : 'Seu catálogo ainda está vazio.' }}
        </p>
        <TransitionGroup v-else tag="ul" name="lista" class="max-h-96 divide-y divide-linha overflow-y-auto border border-linha">
            <li v-for="item in itens" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <span>
                    <span class="font-semibold">{{ item.nome }}</span>
                    <span class="block font-estreita text-xs text-grafite/70">R$ {{ item.preco }} por {{ item.unidade }}</span>
                </span>
                <span v-if="incluidos.includes(item.id)" class="font-estreita text-sm text-tinta-verde">✓ Incluído</span>
                <button v-else type="button" class="botao-secundario px-3 py-1" @click="emit('incluir', item)">Incluir</button>
            </li>
        </TransitionGroup>
        <p class="mt-2 text-xs text-grafite/70">Mostra até 10 por vez; digite para encontrar outros. Só itens ativos.</p>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="botao" @click="emit('fechar')">Concluir</button>
            <button type="button" class="botao-secundario" @click="emit('cadastrar', termo.trim())">+ Cadastrar novo item</button>
        </div>
    </Modal>
</template>
