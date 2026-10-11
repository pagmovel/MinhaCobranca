<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    itens: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    tipos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });

const filtrar = () => router.get('/produtos-servicos', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Produtos e serviços</h1>
            <Link href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="filtrar">
            <Campo id="nome" rotulo="Nome" class="sm:col-span-2">
                <input id="nome" v-model="filtro.nome" type="search" class="entrada">
            </Campo>
            <Campo id="tipo" rotulo="Tipo">
                <select id="tipo" v-model="filtro.tipo" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="situacao" rotulo="Situação">
                <select id="situacao" v-model="filtro.situacao" class="entrada">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </Campo>
            <div class="sm:col-span-4">
                <button class="botao-secundario">Filtrar</button>
            </div>
        </form>

        <p v-if="!itens.length" class="border border-linha bg-white px-4 py-3 text-sm">
            Nenhum produto ou serviço com estes filtros. Cadastre o que você vende para montar contratos por itens.
        </p>

        <TransitionGroup v-else tag="ul" name="lista" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="item in itens" :key="item.id" :class="{ 'opacity-60': !item.ativo }">
                <Link :href="`/produtos-servicos/${item.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ item.nome }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ item.preco }} por {{ item.unidade }}</span>
                    <span class="font-estreita text-xs text-grafite/70">{{ item.tipo }}{{ item.ativo ? '' : ', inativo' }}</span>
                </Link>
            </li>
        </TransitionGroup>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
