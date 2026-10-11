<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { periodoAoRedor } from '../../filtroPeriodo.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    ufs: { type: Array, required: true },
    modelos: { type: Array, required: true },
});

const PADROES = { q: '', cidade: '', uf: '', modelo: '', contrato: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
// A mensagem de vazio segue os filtros do resultado na tela, não o que está sendo digitado (a busca espera 400 ms).
const resultadoFiltrado = computed(() => filtrosAlterados(resumo.value?.filtros ?? {}, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/clientes/dados', filtro, componente: 'Clientes/Index', endereco: '/clientes',
});
// Texto busca 400 ms depois de parar de digitar; Enter e "Filtrar" buscam na hora.
const buscarAoDigitar = adiar(filtrar);
const periodo = periodoAoRedor();
const cobrancasDo = (cliente) => `/cobrancas?${new URLSearchParams({ cliente_id: cliente.id, ...periodo })}`;
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="q" rotulo="Nome ou CPF/CNPJ" class="sm:col-span-3">
                <input id="q" v-model="filtro.q" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="cidade" rotulo="Cidade" class="sm:col-span-2">
                <input id="cidade" v-model="filtro.cidade" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="uf" rotulo="UF">
                <select id="uf" v-model="filtro.uf" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                </select>
            </Campo>
            <Campo id="modelo" rotulo="Modelo do recibo" class="sm:col-span-3">
                <select id="modelo" v-model="filtro.modelo" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="contrato" rotulo="Contrato" class="sm:col-span-3">
                <select id="contrato" v-model="filtro.contrato" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="com">Com contrato ativo</option>
                    <option value="sem">Sem contrato ativo</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/clientes" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando clientes…"
            :vazio="resultadoFiltrado ? 'Nenhum cliente com estes filtros.' : 'Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <!-- Tabela a partir de 1280 px; abaixo disso, cada cliente vira um cartão (mesmo HTML, outro display). -->
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cidade/UF</th>
                            <th scope="col" class="px-3 py-2 font-medium">Recibo</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Contratos ativos</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="cliente in linhas" :key="cliente.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ cliente.nome }}</p>
                                <p class="font-estreita text-xs tabular-nums text-grafite/80">{{ cliente.documento }}</p>
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">{{ cliente.cidade || 'Sem cidade' }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">Recibo </span>{{ cliente.modelo }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/80 xl:px-3 xl:py-3 xl:text-right xl:text-sm">
                                {{ cliente.contratos_ativos }} <span class="xl:hidden">{{ cliente.contratos_ativos === 1 ? 'contrato ativo' : 'contratos ativos' }}</span>
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/clientes/${cliente.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${cliente.nome}`">
                                        <Link :href="`/contratos?cliente_id=${cliente.id}`" :class="itemMenu">Ver contratos</Link>
                                        <Link :href="cobrancasDo(cliente)" :class="itemMenu">Ver cobranças</Link>
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
