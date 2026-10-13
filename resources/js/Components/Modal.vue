<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

/**
 * Janela por cima do formulário (cadastro rápido, catálogo). O formulário de fundo fica intacto.
 * Fecha com Esc ou pelo botão; clique fora não fecha (não perder o que foi digitado por acidente).
 */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    titulo: { type: String, required: true },
    largura: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['fechar']);

const painel = ref(null);
const idTitulo = `${useId()}-titulo`;
let focoAnterior = null;
let temporizadorDeTravamento = null;

const focaveis = () => [...(painel.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? [])];

function teclado(evento) {
    if (evento.key === 'Escape') {
        evento.stopPropagation();
        emit('fechar');
    } else if (evento.key === 'Tab') {
        // Foco preso dentro da janela enquanto ela está aberta.
        const lista = focaveis();
        if (!lista.length) {
            return;
        }
        const primeiro = lista[0];
        const ultimo = lista[lista.length - 1];
        // Foco fora da janela (botão que sumiu, clique no fundo): volta para dentro.
        if (!painel.value?.contains(document.activeElement)) {
            evento.preventDefault();
            primeiro.focus();
        } else if (evento.shiftKey && document.activeElement === primeiro) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primeiro.focus();
        }
    }
}

watch(() => props.aberto, async (aberto) => {
    clearTimeout(temporizadorDeTravamento);
    if (aberto) {
        focoAnterior = document.activeElement;
        document.body.style.overflow = 'hidden';
        // No documento, não no fundo: Esc e Tab valem mesmo se o foco cair no body.
        document.addEventListener('keydown', teclado);
        await nextTick();
        (painel.value?.querySelector('[autofocus]') ?? focaveis()[0])?.focus();
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', teclado);
        focoAnterior?.focus?.();

        // Rede de segurança: numa aba em segundo plano (ex.: "Abrir WhatsApp" tira o foco desta aba) o Chrome para de
        // rodar requestAnimationFrame, do qual a transição de saída do Vue depende para aplicar as classes CSS — ela
        // trava no meio pra sempre, com o fundo escurecido preso na tela (achado real, Fase 24, T24.9). Uma ligação
        // reativa (`:style`/`:class`) não chega a esse elemento: o `<Transition>` mantém um retrato congelado do vnode
        // durante a saída, sem reagir a mudanças de outros dados. Só a manipulação direta do DOM, fora do Vue, funciona.
        temporizadorDeTravamento = setTimeout(() => {
            document.getElementById(idTitulo)?.closest('.fixed.inset-0')?.style.setProperty('display', 'none');
        }, 500);
    }
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(temporizadorDeTravamento);
    document.body.style.overflow = '';
    document.removeEventListener('keydown', teclado);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="aberto" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-grafite/40 px-4 py-10">
                <div
                    ref="painel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="idTitulo"
                    class="w-full border border-linha bg-white p-6 shadow-lg"
                    :class="largura"
                >
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 :id="idTitulo" class="text-lg font-semibold">{{ titulo }}</h2>
                        <button type="button" class="botao-secundario px-2 py-1" aria-label="Fechar" @click="emit('fechar')">✕</button>
                    </div>
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
