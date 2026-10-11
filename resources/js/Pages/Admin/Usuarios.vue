<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { acoesDoUsuario } from '../../acoesUsuario.js';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({ filtros: { type: Object, required: true } });

const PADROES = { q: '', status: '' };
const SITUACAO = { pendente: ['Aguardando liberação', 'laranja'], aprovado: ['Liberado', 'verde'], bloqueado: ['Bloqueado', 'vermelho'] };
const ROTULO = { aprovar: 'Liberar acesso', reativar: 'Reativar' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas: linhasDaPagina, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/admin/usuarios/dados', filtro, componente: 'Admin/Usuarios', endereco: '/admin/usuarios',
    aoMostrar: () => { confirmando.value = null; },
});
const buscarAoDigitar = adiar(filtrar);
const linhas = computed(() => linhasDaPagina.value.map((usuario) => ({ ...usuario, acoes: acoesDoUsuario(usuario) })));

// "Bloquear" é ação destrutiva: confirmação no segundo clique, na própria linha (padrão de usabilidade 8).
const confirmando = ref(null);
// A resposta é o redirect do Inertia: o evento "success" recarrega a lista (usarListaPaginada).
const acao = (id, nome) => router.post(`/admin/usuarios/${id}/${nome}`, {}, { preserveScroll: true, onFinish: () => { confirmando.value = null; } });
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Usuários</h1>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="q" rotulo="Nome ou e-mail" class="sm:col-span-2">
                <input id="q" v-model="filtro.q" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option value="pendente">Aguardando liberação</option>
                    <option value="aprovado">Liberado</option>
                    <option value="bloqueado">Bloqueado</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-end gap-3">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/admin/usuarios" class="pb-2 font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista :estado="estado" :falha="falha" carregando="Carregando usuários…" vazio="Nenhum usuário com estes filtros." @tentar="recarregar">
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Nome</th>
                            <th scope="col" class="px-3 py-2 font-medium">E-mail</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cadastro</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="usuario in linhas" :key="usuario.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ usuario.nome }} <span v-if="usuario.is_admin" class="font-estreita text-xs font-normal text-grafite/70">administrador</span></p>
                            </td>
                            <td class="text-grafite/80 xl:px-3 xl:py-3">{{ usuario.email }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">cadastro em </span>{{ usuario.cadastrado_em }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="SITUACAO[usuario.status][0]" :tom="SITUACAO[usuario.status][1]" />
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div v-if="confirmando === usuario.id" class="flex flex-wrap items-center gap-2 xl:justify-end">
                                    <button type="button" class="botao-perigo-confirmar" @click="acao(usuario.id, 'bloquear')">Confirmar bloqueio</button>
                                    <button type="button" class="botao-secundario" @click="confirmando = null">Cancelar</button>
                                </div>
                                <div v-else class="flex items-center gap-2 xl:justify-end">
                                    <button v-if="usuario.acoes.principal" type="button" :class="['botao', botaoPrincipal]" @click="acao(usuario.id, 'aprovar')">
                                        {{ ROTULO[usuario.acoes.principal] }}
                                    </button>
                                    <MenuAcoes v-if="usuario.acoes.menu.length" v-slot="{ fechar }" :rotulo="`Mais ações: ${usuario.nome}`">
                                        <button type="button" :class="[itemMenu, 'text-tinta-vermelha']" @click="fechar(); confirmando = usuario.id">Bloquear</button>
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
