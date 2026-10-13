<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { itemBloqueado } from '../progresso.js';
import ModalBoasVindas from '../Components/ModalBoasVindas.vue';

const page = usePage();
const usuario = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const menuAberto = ref(false);

const itens = computed(() => [
    { href: '/painel', rotulo: 'Painel' },
    { href: '/cobrancas', rotulo: 'Cobranças', bloqueado: itemBloqueado('/cobrancas', page.props.progresso) },
    { href: '/contratos', rotulo: 'Contratos', bloqueado: itemBloqueado('/contratos', page.props.progresso) },
    { href: '/clientes', rotulo: 'Clientes', bloqueado: itemBloqueado('/clientes', page.props.progresso) },
    { href: '/produtos-servicos', rotulo: 'Produtos e serviços', bloqueado: itemBloqueado('/produtos-servicos', page.props.progresso) },
    { href: '/relatorios', rotulo: 'Relatórios' },
    { href: '/auditoria', rotulo: 'Auditoria' },
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
                    <span
                        v-if="item.bloqueado"
                        class="flex items-center justify-between px-5 py-2 text-sm text-grafite/50"
                        :title="item.href === '/contratos' || item.href === '/cobrancas'
                            ? 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.'
                            : 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.'"
                    >
                        🔒 {{ item.rotulo }}
                    </span>
                    <Link
                        v-else
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

        <main class="w-full min-w-0 px-4 py-6 md:px-8">
            <Transition name="surgir">
                <p v-if="flash.sucesso" :key="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-white px-4 py-2 text-sm">
                    {{ flash.sucesso }}
                </p>
            </Transition>
            <Transition name="surgir">
                <p v-if="flash.erro" :key="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-white px-4 py-2 text-sm">
                    {{ flash.erro }}
                </p>
            </Transition>
            <!-- Anima ao trocar de tela; mudar só o filtro (mesma tela) não remonta nada. -->
            <div :key="page.component" class="aparecer">
                <slot />
            </div>
        </main>

        <ModalBoasVindas v-if="page.props.mostrarApresentacao" :progresso="page.props.progresso" />
    </div>
</template>
