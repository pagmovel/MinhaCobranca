<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { explicacaoDoBloqueio } from '../menuPrincipal.js';
import { iniciaisDoNome } from '../iniciais.js';
import LogoRecibo from './LogoRecibo.vue';

/**
 * Casca lateral (Talão e Almanaque): no celular vira cabeçalho com o botão "Menu"; do `md` para cima é a coluna à
 * esquerda, com o botão do usuário no rodapé. Dois nós na raiz (cabeçalho e menu): ficam lado a lado na grade do
 * `CamadaApp`, que mantém o `<main>` sempre no mesmo lugar da árvore.
 */
defineProps({
    itens: { type: Array, required: true },
    usuario: { type: Object, required: true },
    painelAberto: { type: Boolean, default: false },
});
defineEmits(['abrir-painel']);

const page = usePage();
const menuAberto = ref(false);
const ativo = (href) => page.url.startsWith(href);
</script>

<template>
    <header class="flex items-center justify-between border-b border-menu-linha bg-menu-fundo px-4 py-3 text-menu-texto md:hidden">
        <span class="items-center gap-2 font-semibold [display:var(--marca-exibicao,block)]">
            <LogoRecibo class="size-6" />
            Gerenciador de Cobranças
        </span>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="grid size-[30px] place-items-center rounded-full bg-carbono text-xs font-semibold text-sobre-acao focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono"
                :aria-label="usuario.name"
                data-botao-do-usuario
                aria-haspopup="dialog"
                :aria-expanded="painelAberto"
                @click="$emit('abrir-painel', $event)"
            >
                <span aria-hidden="true">{{ iniciaisDoNome(usuario.name) }}</span>
            </button>
            <button
                type="button"
                class="botao-secundario px-3 py-1"
                :aria-expanded="menuAberto"
                aria-controls="menu-principal"
                @click="menuAberto = !menuAberto"
            >
                Menu
            </button>
        </div>
    </header>

    <nav
        id="menu-principal"
        aria-label="Principal"
        :class="[menuAberto ? 'block' : 'hidden', 'border-b border-menu-linha bg-menu-fundo text-menu-texto md:flex md:flex-col md:border-b-0 md:border-r']"
    >
        <p class="hidden items-center gap-3 px-5 pt-6 pb-4 font-semibold md:[display:var(--marca-exibicao,block)]">
            <LogoRecibo />
            Gerenciador de Cobranças
        </p>
        <ul class="flex flex-col py-2">
            <li v-for="item in itens" :key="item.href">
                <span
                    v-if="item.bloqueado"
                    class="flex items-center justify-between px-5 py-2 text-sm text-menu-texto/50"
                    :title="explicacaoDoBloqueio(item.href)"
                >
                    🔒 {{ item.rotulo }}
                </span>
                <Link
                    v-else
                    :href="item.href"
                    class="flex items-center justify-between px-5 py-2 text-sm hover:bg-menu-ativo"
                    :class="ativo(item.href) ? 'bg-menu-ativo font-semibold text-menu-texto-ativo' : ''"
                    :aria-current="ativo(item.href) ? 'page' : undefined"
                    @click="menuAberto = false"
                >
                    {{ item.rotulo }}
                    <span
                        v-if="item.contador"
                        class="rounded-full bg-tinta-vermelha px-2 text-xs font-semibold text-white"
                        :aria-label="`${item.contador} cadastros aguardando liberação`"
                    >{{ item.contador }}</span>
                </Link>
            </li>
        </ul>
        <!-- sticky: em página longa o botão fica no rodapé da janela, não no fim da página. -->
        <div class="mt-auto hidden border-t border-menu-linha bg-menu-fundo md:sticky md:bottom-0 md:block">
            <button
                type="button"
                class="flex w-full items-center gap-3 px-5 py-3 text-left text-sm hover:bg-menu-ativo focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-carbono"
                data-botao-do-usuario
                aria-haspopup="dialog"
                :aria-expanded="painelAberto"
                @click="$emit('abrir-painel', $event)"
            >
                <span class="grid size-8 flex-none place-items-center rounded-full bg-carbono text-xs font-semibold text-sobre-acao" aria-hidden="true">
                    {{ iniciaisDoNome(usuario.name) }}
                </span>
                <span class="min-w-0 flex-1 truncate">{{ usuario.name }}</span>
            </button>
        </div>
    </nav>
</template>
