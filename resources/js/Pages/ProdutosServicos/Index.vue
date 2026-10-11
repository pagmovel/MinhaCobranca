<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    tipos: { type: Array, required: true },
});

const PADROES = { nome: '', tipo: '', situacao: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/produtos-servicos/dados', filtro, componente: 'ProdutosServicos/Index', endereco: '/produtos-servicos',
});
const buscarAoDigitar = adiar(filtrar);
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Produtos e serviços</h1>
            <Link href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="nome" rotulo="Nome" class="sm:col-span-2">
                <input id="nome" v-model="filtro.nome" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="tipo" rotulo="Tipo">
                <select id="tipo" v-model="filtro.tipo" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="situacao" rotulo="Situação">
                <select id="situacao" v-model="filtro.situacao" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-4">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/produtos-servicos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando produtos e serviços…"
            :vazio="filtrando ? 'Nenhum produto ou serviço com estes filtros.' : 'Nenhum produto ou serviço ainda. Cadastre o que você vende para montar contratos por itens.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Nome</th>
                            <th scope="col" class="px-3 py-2 font-medium">Tipo</th>
                            <th scope="col" class="px-3 py-2 font-medium">Unidade</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Preço</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Em contratos ativos</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="item in linhas" :key="item.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full font-semibold xl:px-4 xl:py-3">{{ item.nome }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">{{ item.tipo }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">por </span>{{ item.unidade }}</td>
                            <td class="font-semibold whitespace-nowrap tabular-nums xl:px-3 xl:py-3 xl:text-right">R$ {{ item.preco }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="item.ativo ? 'Ativo' : 'Inativo'" :tom="item.ativo ? 'verde' : 'apagado'" />
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/80 xl:px-3 xl:py-3 xl:text-right xl:text-sm">
                                {{ item.em_contratos_ativos }}<span class="xl:hidden"> em contratos ativos</span>
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/produtos-servicos/${item.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${item.nome}`">
                                        <Link :href="`/produtos-servicos/${item.id}/reajuste`" :class="itemMenu">Reajustar preço</Link>
                                        <Link :href="`/contratos?produto_servico_id=${item.id}`" :class="itemMenu">Ver contratos</Link>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
