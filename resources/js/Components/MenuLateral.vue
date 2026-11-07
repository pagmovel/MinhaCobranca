<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { explicacaoDoBloqueio, iconeDoItem } from '../menuPrincipal.js';
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
    pulsando: { type: Boolean, default: false }, // chama a atenção para o botão do nome (popup da novidade)
});
defineEmits(['abrir-painel']);

const page = usePage();
const menuAberto = ref(false);
const ativo = (href) => page.url.startsWith(href);
</script>

<template>
    <header class="flex items-center justify-between border-b border-menu-linha bg-menu-fundo px-4 py-3 text-menu-texto md:hidden">
        <span class="items-center gap-2 font-titulo font-semibold [display:var(--marca-exibicao,block)] [padding-right:var(--marca-folga,0px)]">
            <LogoRecibo class="size-6" />
            Gerenciador de Cobranças
        </span>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="grid size-[30px] place-items-center rounded-full bg-carbono text-xs font-semibold text-sobre-acao focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono"
                :class="pulsando ? 'pulsar-botao' : ''"
                :aria-label="`Minha conta, ${usuario.name}`"
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
        <p class="hidden items-center gap-3 px-5 pt-6 pb-4 font-titulo font-semibold md:[display:var(--marca-exibicao,block)]">
            <LogoRecibo />
            Gerenciador de Cobranças
        </p>
        <!-- Ícone, pílula e contador dourado vêm de variáveis do tema (almanaque.css); sem elas o item é o reto do Talão. -->
        <ul class="flex flex-col py-2 [gap:var(--menu-lista-gap,0px)] [padding-inline:var(--menu-lista-px,0px)]">
            <li v-for="item in itens" :key="item.href">
                <span
                    v-if="item.bloqueado"
                    class="flex items-center justify-between py-2 text-sm text-menu-texto/50 [border-radius:var(--menu-item-raio,0px)] [min-height:var(--menu-item-altura,0px)] [padding-inline:var(--menu-item-px,1.25rem)]"
                    :title="explicacaoDoBloqueio(item.href)"
                >
                    🔒 {{ item.rotulo }}
                </span>
                <Link
                    v-else
                    :href="item.href"
                    class="flex items-center py-2 text-sm hover:bg-menu-ativo [border-radius:var(--menu-item-raio,0px)] [gap:var(--menu-item-gap,0px)] [min-height:var(--menu-item-altura,0px)] [padding-inline:var(--menu-item-px,1.25rem)]"
                    :class="ativo(item.href) ? 'bg-menu-ativo font-semibold text-menu-texto-ativo' : ''"
                    :aria-current="ativo(item.href) ? 'page' : undefined"
                    @click="menuAberto = false"
                >
                    <svg class="flex-none [display:var(--menu-icone-exibicao,none)]" aria-hidden="true" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="iconeDoItem(item.href)" />
                    </svg>
                    {{ item.rotulo }}
                    <span
                        v-if="item.contador"
                        class="ml-auto rounded-full bg-contador-fundo px-2 text-center text-xs leading-[var(--contador-altura,1rem)] font-semibold text-contador-texto [height:var(--contador-altura,auto)] [min-width:var(--contador-largura,0px)]"
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
                :class="pulsando ? 'pulsar-botao-linha' : ''"
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
