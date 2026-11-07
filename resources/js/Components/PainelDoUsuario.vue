<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { proximoDaArmadilha, saindoDaArmadilha } from '../armadilhaDeFoco.js';
import { botaoDoUsuarioVisivel } from '../botaoDoUsuario.js';
import { iniciaisDoNome } from '../iniciais.js';
import { haJanelaPorCima, liberarRolagem, quemFechaComEsc, travarRolagem } from '../pilhaDeDialogos.js';
import ModalAlterarSenha from './ModalAlterarSenha.vue';
import SeletorDeTema from './SeletorDeTema.vue';

/**
 * Painel lateral do usuário (abre pelo botão do nome no menu). Mesmo contrato do `Modal.vue`: foco preso dentro,
 * Esc e clique no fundo fecham, o foco volta ao botão que abriu e a rolagem do corpo fica travada.
 * O corpo é em seções ("Aparência" e "Alterar senha", nesta ordem) e o "Sair" fica no rodapé.
 * "Alterar senha" é uma linha-botão que abre o popup `ModalAlterarSenha` por cima, com o painel aberto por trás. Com o
 * popup aberto o painel não reage ao teclado (Esc fecha só o popup; Tab não puxa o foco de volta), e a trava de
 * rolagem é contada por dono (`pilhaDeDialogos.js`): fechar o popup não destrava o corpo.
 */
const props = defineProps({
    aberto: { type: Boolean, required: true },
});

const emit = defineEmits(['fechar']);

const pagina = usePage();
const usuario = computed(() => pagina.props.auth.user);
const painel = ref(null);
const linhaDaSenha = ref(null);
const idTitulo = `${useId()}-titulo`;
const dono = Symbol('painel-do-usuario');
let focoAnterior = null;

const senhaAberta = ref(false);
const senhaAlterada = ref(false); // confirmação ao lado da linha; some quando o painel fecha

function abrirSenha(evento) {
    // Safari e Firefox no Mac não focam o botão no clique; sem isso o popup não saberia a quem devolver o foco.
    evento.currentTarget.focus();
    senhaAberta.value = true;
}

function fecharSenha() {
    senhaAberta.value = false;
    // Além do que o `Modal` já faz: o foco volta à linha mesmo que o `Modal` tenha guardado outro elemento.
    nextTick(() => linhaDaSenha.value?.focus());
}

const focaveis = () => [...(painel.value?.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
) ?? [])].filter((elemento) => elemento.getClientRects().length > 0); // seção recolhida não entra na volta do Tab

function teclado(evento) {
    // O popup (listener registrado depois, no mesmo `document`) cuida de Esc e Tab enquanto estiver aberto; o mesmo vale
    // para qualquer outro `Modal` por cima (Bem-vindo, novidade dos temas, catálogo): sem isto, um Esc fecharia os dois.
    if (quemFechaComEsc(true, senhaAberta.value || haJanelaPorCima(painel.value)) !== 'painel') {
        return;
    }
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
        travarRolagem(dono);
        // No documento, não no painel: Esc e Tab valem mesmo se o foco cair no body.
        document.addEventListener('keydown', teclado);
        await nextTick();
        focaveis()[0]?.focus();
    } else {
        liberarRolagem(dono);
        senhaAberta.value = false;
        senhaAlterada.value = false;
        document.removeEventListener('keydown', teclado);
        // Trocar de tema pode trocar o menu (lateral para superior): o botão que abriu o painel deixa de existir.
        (focoAnterior?.isConnected ? focoAnterior : botaoDoUsuarioVisivel())?.focus?.();
    }
});

onBeforeUnmount(() => {
    liberarRolagem(dono);
    document.removeEventListener('keydown', teclado);
});

const sair = () => router.post('/sair');
</script>

<template>
    <Teleport to="body">
        <Transition name="painel-lateral">
            <!-- z-40: todo `Modal` (z-50), como o popup da senha, fica por cima do painel. -->
            <div v-if="aberto" class="fixed inset-0 z-40 flex justify-end bg-grafite/40" @click.self="emit('fechar')">
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
                        <!-- Seções (cada uma um <section class="px-6 py-5">), nesta ordem: Aparência, Alterar senha. -->
                        <SeletorDeTema />

                        <section class="px-6 py-5">
                            <button
                                ref="linhaDaSenha"
                                type="button"
                                aria-haspopup="dialog"
                                class="-mx-3 flex w-[calc(100%+1.5rem)] items-center justify-between gap-4 rounded-(--raio-controle) px-3 py-2 text-left transition-colors hover:bg-carbono/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono"
                                @click="abrirSenha"
                            >
                                <span class="min-w-0">
                                    <span class="block font-titulo text-base font-semibold">Alterar senha</span>
                                    <span class="block text-sm text-grafite/70">Troque a senha de acesso</span>
                                </span>
                                <!-- Chave: o assunto da linha é a senha (a caixa com seta de "link externo" sugeria sair da página). -->
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-grafite/60">
                                    <circle cx="5.5" cy="10.5" r="3" />
                                    <path d="M7.7 8.3 13.5 2.5M11.5 4.5l1.8 1.8M9.6 6.4l1.5 1.5" />
                                </svg>
                            </button>
                            <div role="status">
                                <Transition name="surgir">
                                    <p v-if="senhaAlterada && !senhaAberta" class="mt-3 border-l-4 border-tinta-verde pl-3 text-sm">Senha alterada agora há pouco.</p>
                                </Transition>
                            </div>
                        </section>
                    </div>

                    <footer class="border-t border-linha px-6 py-4">
                        <button type="button" class="botao-perigo w-full" @click="sair">Sair</button>
                    </footer>
                </div>

                <ModalAlterarSenha :aberto="senhaAberta" @fechar="fecharSenha" @alterada="senhaAlterada = true" />
            </div>
        </Transition>
    </Teleport>
</template>
