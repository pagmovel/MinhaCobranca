<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const menuAberto = ref(false);

const itens = computed(() => [
    { href: '/painel', rotulo: 'Painel' },
    { href: '/cobrancas', rotulo: 'Cobranças' },
    { href: '/contratos', rotulo: 'Contratos' },
    { href: '/clientes', rotulo: 'Clientes' },
    { href: '/produtos-servicos', rotulo: 'Produtos e serviços' },
    { href: '/perfil', rotulo: 'Meu perfil' },
    ...(usuario.value?.is_admin
        ? [{ href: '/admin/usuarios', rotulo: 'Usuários', contador: page.props.pendentes }]
        : []),
]);

const ativo = (href) => page.url.startsWith(href);
const sair = () => router.post('/sair');
</script>

<template>
    <div class="min-h-screen md:grid md:grid-cols-[13rem_1fr]">
        <header class="flex items-center justify-between border-b border-linha bg-white px-4 py-3 md:hidden">
            <span class="font-semibold">Gerenciador de Cobranças</span>
            <button
                type="button"
                class="botao-secundario px-3 py-1"
                :aria-expanded="menuAberto"
                aria-controls="menu-principal"
                @click="menuAberto = !menuAberto"
            >
                Menu
            </button>
        </header>

        <nav
            id="menu-principal"
            aria-label="Principal"
            :class="[menuAberto ? 'block' : 'hidden', 'border-b border-linha bg-white md:block md:border-b-0 md:border-r']"
        >
            <p class="hidden px-5 pt-6 pb-4 font-semibold md:block">Gerenciador de Cobranças</p>
            <ul class="flex flex-col py-2">
                <li v-for="item in itens" :key="item.href">
                    <Link
                        :href="item.href"
                        class="flex items-center justify-between px-5 py-2 text-sm hover:bg-papel"
                        :class="ativo(item.href) ? 'bg-papel font-semibold text-carbono' : ''"
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
                <li>
                    <button type="button" class="w-full px-5 py-2 text-left text-sm hover:bg-papel" @click="sair">
                        Sair
                    </button>
                </li>
            </ul>
        </nav>

        <main class="mx-auto w-full max-w-5xl px-4 py-6 md:px-8">
            <p v-if="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-white px-4 py-2 text-sm">
                {{ flash.sucesso }}
            </p>
            <p v-if="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-white px-4 py-2 text-sm">
                {{ flash.erro }}
            </p>
            <slot />
        </main>
    </div>
</template>
