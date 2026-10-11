<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    contratos: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    modalidades: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    produtos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => Object.values(props.filtros).some((valor) => valor !== ''));

const filtrar = () => router.get('/contratos', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Contratos</h1>
            <Link href="/contratos/create" class="botao">Novo contrato</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="filtrar">
            <Campo id="cliente_id" rotulo="Cliente" class="sm:col-span-3">
                <BuscaAutosugestao id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />
            </Campo>
            <Campo id="situacao" rotulo="Situação" class="sm:col-span-3">
                <select id="situacao" v-model="filtro.situacao" class="entrada">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="encerrados">Encerrados</option>
                </select>
            </Campo>
            <Campo id="modalidade" rotulo="Modalidade" class="sm:col-span-2">
                <select id="modalidade" v-model="filtro.modalidade" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="modalidade in modalidades" :key="modalidade.valor" :value="modalidade.valor">{{ modalidade.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="forma_pagamento" rotulo="Forma de pagamento" class="sm:col-span-2">
                <select id="forma_pagamento" v-model="filtro.forma_pagamento" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="forma in formasPagamento" :key="forma.valor" :value="forma.valor">{{ forma.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="produto_servico_id" rotulo="Produto ou serviço" class="sm:col-span-2">
                <select id="produto_servico_id" v-model="filtro.produto_servico_id" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="produto in produtos" :key="produto.id" :value="produto.id">{{ produto.nome }}</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/contratos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <p v-if="!contratos.length" class="border border-linha bg-white px-4 py-3 text-sm">
            {{ filtrando
                ? 'Nenhum contrato com estes filtros.'
                : 'Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.' }}
        </p>

        <TransitionGroup v-else tag="ul" name="lista" class="divide-y divide-linha border border-linha bg-white">
            <li v-for="contrato in contratos" :key="contrato.id" :class="{ 'opacity-60': !contrato.ativo }">
                <Link :href="`/contratos/${contrato.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ contrato.cliente }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ contrato.valor }}</span>
                    <span class="text-sm text-grafite/80">{{ contrato.resumo }}</span>
                    <span class="font-estreita text-xs text-grafite/70 sm:text-right">
                        {{ contrato.periodicidade }}, {{ contrato.modalidade }}, vence dia {{ contrato.dia_vencimento }}{{ contrato.ativo ? '' : ', encerrado' }}
                    </span>
                </Link>
            </li>
        </TransitionGroup>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
