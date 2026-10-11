<script setup>
import { ref, watch } from 'vue';
import { criarIndicadorComAtraso, pendentes } from '../carregando.js';

const ativo = ref(false);
watch(pendentes, criarIndicadorComAtraso(pendentes, (visivel) => { ativo.value = visivel; }), { immediate: true });
</script>

<template>
    <!-- Região viva sempre presente (o leitor de tela anuncia a troca); o aviso visível entra e sai com animação.
         Acima de tudo, inclusive dos modais (z-50), e sem bloquear cliques (pointer-events-none). -->
    <div class="pointer-events-none fixed inset-x-0 top-3 z-[60] flex justify-center px-4" role="status" aria-live="polite">
        <Transition name="surgir">
            <p v-if="ativo" class="flex items-center gap-2 rounded-full border border-carbono bg-carbono px-4 py-2 text-sm font-semibold text-white shadow-lg">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true" />
                Carregando…
            </p>
        </Transition>
    </div>
</template>
