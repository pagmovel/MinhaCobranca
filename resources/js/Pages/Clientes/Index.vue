<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    clientes: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    ufs: { type: Array, required: true },
    modelos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => Object.values(props.filtros).some((valor) => valor !== ''));

const filtrar = () => router.get('/clientes', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="filtrar">
            <Campo id="q" rotulo="Nome ou CPF/CNPJ" class="sm:col-span-3">
                <input id="q" v-model="filtro.q" type="search" class="entrada">
            </Campo>
            <Campo id="cidade" rotulo="Cidade" class="sm:col-span-2">
                <input id="cidade" v-model="filtro.cidade" type="search" class="entrada">
            </Campo>
            <Campo id="uf" rotulo="UF">
                <select id="uf" v-model="filtro.uf" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                </select>
            </Campo>
            <Campo id="modelo" rotulo="Modelo do recibo" class="sm:col-span-3">
                <select id="modelo" v-model="filtro.modelo" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="contrato" rotulo="Contrato" class="sm:col-span-3">
                <select id="contrato" v-model="filtro.contrato" class="entrada">
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

        <p v-if="!clientes.length" class="border border-linha bg-white px-4 py-3 text-sm">
            {{ filtrando ? 'Nenhum cliente com estes filtros.' : 'Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.' }}
        </p>

        <TransitionGroup v-else tag="ul" name="lista" class="divide-y divide-linha border border-linha bg-white">
            <li v-for="cliente in clientes" :key="cliente.id">
                <Link :href="`/clientes/${cliente.id}/edit`" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ cliente.nome }}</span>
                    <span class="font-estreita text-sm tabular-nums text-grafite/80">{{ cliente.documento }}</span>
                    <span class="w-full font-estreita text-xs text-grafite/70">{{ cliente.cidade || 'Sem cidade' }}, recibo {{ cliente.modelo }}</span>
                </Link>
            </li>
        </TransitionGroup>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
