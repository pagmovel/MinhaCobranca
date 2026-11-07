<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { proximoDaArmadilha, saindoDaArmadilha } from '../armadilhaDeFoco.js';
import { botaoDoUsuarioVisivel } from '../botaoDoUsuario.js';
import { iniciaisDoNome } from '../iniciais.js';
import FormularioDeSenha from './FormularioDeSenha.vue';
import SeletorDeTema from './SeletorDeTema.vue';

/**
 * Painel lateral do usuário (abre pelo botão do nome no menu). Mesmo contrato do `Modal.vue`: foco preso dentro,
 * Esc e clique no fundo fecham, o foco volta ao botão que abriu e a rolagem do corpo fica travada.
 * O corpo é em seções ("Aparência" e "Alterar senha", nesta ordem) e o "Sair" fica no rodapé.
 */
const props = defineProps({
    aberto: { type: Boolean, required: true },
});

const emit = defineEmits(['fechar']);

const pagina = usePage();
const usuario = computed(() => pagina.props.auth.user);
const painel = ref(null);
const idTitulo = `${useId()}-titulo`;
let focoAnterior = null;

const focaveis = () => [...(painel.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? [])].filter((elemento) => elemento.getClientRects().length > 0); // seção recolhida não entra na volta do Tab

function teclado(evento) {
    if (evento.key === 'Escape') {
        evento.stopPropagation();
        emit('fechar');
    } else if (evento.key === 'Tab') {
        const lista = focaveis();
        const indice = lista.indexOf(document.activeElement);
        // Só nas bordas (ou com o foco fora do painel): no meio vale a ordem natural do navegador.
        if (saindoDaArmadilha(lista.length, indice, evento.shiftKey)) {
            evento.preventDefault();
            lista[proximoDaArmadilha(lista.length, indice, evento.shiftKey)]?.focus();
        }
    }
}

// Sem `immediate`: o painel nasce fechado e não pode apagar a trava de rolagem de um modal que já esteja aberto.
watch(() => props.aberto, async (aberto) => {
    if (aberto) {
        focoAnterior = document.activeElement;
        document.body.style.overflow = 'hidden';
        // No documento, não no painel: Esc e Tab valem mesmo se o foco cair no body.
        document.addEventListener('keydown', teclado);
        await nextTick();
        focaveis()[0]?.focus();
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', teclado);
        // Trocar de tema pode trocar o menu (lateral para superior): o botão que abriu o painel deixa de existir.
        (focoAnterior?.isConnected ? focoAnterior : botaoDoUsuarioVisivel())?.focus?.();
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', teclado);
});

const sair = () => router.post('/sair');
</script>

<template>
    <Teleport to="body">
        <Transition name="painel-lateral">
            <div v-if="aberto" class="fixed inset-0 z-50 flex justify-end bg-grafite/40" @click.self="emit('fechar')">
                <div
                    ref="painel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="idTitulo"
                    class="flex h-full w-[min(26rem,100%)] flex-col border-l border-linha min-[417px]:rounded-l-(--raio-bloco) bg-superficie shadow-lg"
                >
                    <header class="flex items-start gap-4 border-b border-linha px-6 py-5">
                        <span class="grid size-12 flex-none place-items-center rounded-full bg-carbono font-titulo text-base font-semibold text-sobre-acao" aria-hidden="true">
                            {{ iniciaisDoNome(usuario.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 :id="idTitulo" class="sr-only">Minha conta</h2>
                            <p class="font-titulo text-lg leading-snug font-semibold [overflow-wrap:anywhere]">{{ usuario.name }}</p>
                            <p class="text-sm text-grafite/70 [overflow-wrap:anywhere]">{{ usuario.email }}</p>
                        </div>
                        <button type="button" class="botao-secundario px-2 py-1" aria-label="Fechar painel" @click="emit('fechar')">✕</button>
                    </header>

                    <div class="flex-1 divide-y divide-linha overflow-y-auto">
                        <!-- Seções (cada uma um <section class="px-6 py-5"> com <h3>), nesta ordem: Aparência, Alterar senha. -->
                        <SeletorDeTema />
                        <FormularioDeSenha />
                    </div>

                    <footer class="border-t border-linha px-6 py-4">
                        <button type="button" class="botao-perigo w-full" @click="sair">Sair</button>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
