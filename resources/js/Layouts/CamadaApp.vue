<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { botaoDoUsuarioVisivel } from '../botaoDoUsuario.js';
import { itensDoMenu } from '../menuPrincipal.js';
import { novidadeDispensada } from '../novidadeDosTemas.js';
import { usarTema } from '../usarTema.js';
import MenuLateral from '../Components/MenuLateral.vue';
import MenuSuperior from '../Components/MenuSuperior.vue';
import ModalBoasVindas from '../Components/ModalBoasVindas.vue';
import ModalNovidadeTemas from '../Components/ModalNovidadeTemas.vue';
import PainelDoUsuario from '../Components/PainelDoUsuario.vue';

const page = usePage();
const usuario = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const itens = computed(() => itensDoMenu(page.props.progresso, usuario.value?.is_admin, page.props.pendentes));

// A casca vem do tema. Só o `<nav>` troca: o `<main>` fica sempre no mesmo lugar da árvore, então trocar de casca
// não remonta a página nem apaga um formulário preenchido.
const { atual } = usarTema();
const superior = computed(() => atual.value?.casca === 'superior');
const menu = computed(() => (superior.value ? MenuSuperior : MenuLateral));

const painelAberto = ref(false);
function abrirPainel(evento) {
    // Safari e Firefox no Mac não focam o botão no clique; sem isso o painel não saberia a quem devolver o foco.
    evento.currentTarget.focus();
    painelAberto.value = true;
}

// Depois do popup da novidade dos temas: "Escolher meu tema agora" abre o painel (a seção Aparência é a primeira) e
// "Agora não" faz o botão do nome pulsar por ~4 s (3 pulsos de 1,1 s; sem movimento com prefers-reduced-motion).
const pulsando = ref(false);
let temporizadorDoPulso = null;
async function depoisDaNovidade(abrirPainel) {
    await nextTick(); // o popup já saiu e devolveu a rolagem; o foco vai para o botão que o painel guarda
    botaoDoUsuarioVisivel()?.focus();
    if (abrirPainel) {
        painelAberto.value = true;

        return;
    }
    pulsando.value = true;
    clearTimeout(temporizadorDoPulso);
    temporizadorDoPulso = setTimeout(() => { pulsando.value = false; }, 4000);
}
onBeforeUnmount(() => clearTimeout(temporizadorDoPulso));
</script>

<template>
    <div :class="superior ? 'min-h-screen' : 'min-h-screen md:grid md:grid-cols-[13rem_1fr]'">
        <component
            :is="menu"
            :itens="itens"
            :usuario="usuario"
            :painel-aberto="painelAberto"
            :pulsando="pulsando"
            @abrir-painel="abrirPainel"
        />

        <main class="w-full min-w-0 px-4 py-6 md:px-8">
            <Transition name="surgir">
                <p v-if="flash.sucesso" :key="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-superficie px-4 py-2 text-sm">
                    {{ flash.sucesso }}
                </p>
            </Transition>
            <Transition name="surgir">
                <p v-if="flash.erro" :key="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-superficie px-4 py-2 text-sm">
                    {{ flash.erro }}
                </p>
            </Transition>
            <!-- Anima ao trocar de tela; mudar só o filtro (mesma tela) não remonta nada. -->
            <div :key="page.component" class="aparecer">
                <slot />
            </div>
        </main>

        <ModalBoasVindas v-if="page.props.mostrarApresentacao" :progresso="page.props.progresso" />
        <!-- Nunca junto da apresentação inicial: o servidor já garante, e aqui também. -->
        <ModalNovidadeTemas
            v-if="page.props.mostrarNovidadeTemas && !page.props.mostrarApresentacao && !novidadeDispensada"
            :temas="page.props.temas"
            :casca="atual?.casca ?? 'lateral'"
            @escolher="depoisDaNovidade(true)"
            @depois="depoisDaNovidade(false)"
        />
        <PainelDoUsuario :aberto="painelAberto" @fechar="painelAberto = false" />
    </div>
</template>
