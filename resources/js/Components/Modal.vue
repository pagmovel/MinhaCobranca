<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { liberarRolagem, travarRolagem } from '../pilhaDeDialogos.js';

/**
 * Janela por cima do formulário (cadastro rápido, catálogo). O formulário de fundo fica intacto.
 * Fecha com Esc ou pelo botão; clique fora não fecha (não perder o que foi digitado por acidente), a não ser que a
 * janela só informe e não tenha nada digitado (`fecharNoFundo`, como o popup da novidade dos temas).
 * Por padrão a janela fica perto do topo (formulários longos que crescem para baixo); `centralizado` a põe no meio da
 * tela, e se o conteúdo for mais alto que a tela ela começa do topo e rola, sem cortar o título.
 * A rolagem do corpo usa a contagem de `pilhaDeDialogos.js`: fechar esta janela por cima do painel do usuário não
 * destrava o corpo enquanto o painel segue aberto.
 */
const props = defineProps({
    aberto: { type: Boolean, required: true },
    titulo: { type: String, required: true },
    largura: { type: String, default: 'max-w-lg' },
    fecharNoFundo: { type: Boolean, default: false },
    centralizado: { type: Boolean, default: false },
});

const emit = defineEmits(['fechar']);

const painel = ref(null);
const idTitulo = `${useId()}-titulo`;
const dono = Symbol('modal');
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
        travarRolagem(dono);
        // No documento, não no fundo: Esc e Tab valem mesmo se o foco cair no body.
        document.addEventListener('keydown', teclado);
        await nextTick();
        (painel.value?.querySelector('[autofocus]') ?? focaveis()[0])?.focus();
    } else {
        liberarRolagem(dono);
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
    liberarRolagem(dono);
    document.removeEventListener('keydown', teclado);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="aberto"
                class="fixed inset-0 z-50 flex overflow-y-auto bg-grafite/40 px-4 py-10"
                :class="centralizado ? '' : 'items-start justify-center'"
                @click.self="fecharNoFundo && emit('fechar')"
            >
                <div
                    ref="painel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="idTitulo"
                    class="w-full border border-linha bg-superficie p-6 shadow-lg"
                    :class="[largura, centralizado ? 'm-auto' : '']"
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
