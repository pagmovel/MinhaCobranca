<script setup>
import { computed } from 'vue';
import { paginasVisiveis } from '../paginacao.js';

/** Paginador da lista: "Mostrando X–Y de N", Anterior/números/Próxima e "Por página". Usado acima e abaixo da tabela. */
const props = defineProps({
    id: { type: String, required: true },
    pagina: { type: Number, required: true },
    porPagina: { type: Number, required: true },
    total: { type: Number, required: true },
    carregando: { type: Boolean, default: false },
});
const emit = defineEmits(['ir', 'por-pagina']);

const ultima = computed(() => Math.max(1, Math.ceil(props.total / props.porPagina)));
const de = computed(() => (props.pagina - 1) * props.porPagina + 1);
const ate = computed(() => Math.min(props.pagina * props.porPagina, props.total));
</script>

<template>
    <nav :id="id" tabindex="-1" aria-label="Páginas" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm focus:outline-none">
        <p class="flex items-center gap-2 font-estreita text-xs text-grafite/75">
            Mostrando {{ de }}–{{ ate }} de {{ total }}
            <span v-if="carregando" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
            <span v-if="carregando" class="sr-only" role="status">Carregando…</span>
        </p>
        <div v-if="ultima > 1" class="flex flex-wrap items-center gap-1">
            <button type="button" class="botao-secundario px-3" :disabled="pagina <= 1" @click="emit('ir', pagina - 1)">Anterior</button>
            <template v-for="(item, indice) in paginasVisiveis(pagina, ultima)" :key="`${indice}-${item}`">
                <span v-if="item === '…'" class="px-1 text-grafite/60" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    :class="[item === pagina ? 'botao' : 'botao-secundario', 'min-w-10 px-2 tabular-nums']"
                    :aria-current="item === pagina ? 'page' : undefined"
                    :aria-label="`Página ${item}`"
                    @click="emit('ir', item)"
                >
                    {{ item }}
                </button>
            </template>
            <button type="button" class="botao-secundario px-3" :disabled="pagina >= ultima" @click="emit('ir', pagina + 1)">Próxima</button>
        </div>
        <label class="flex items-center gap-2 font-estreita text-xs text-grafite/75">
            Por página
            <select :value="porPagina" class="entrada w-auto py-1" @change="emit('por-pagina', Number($event.target.value))">
                <option v-for="opcao in [25, 50, 100]" :key="opcao" :value="opcao">{{ opcao }}</option>
            </select>
        </label>
    </nav>
</template>
