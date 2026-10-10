<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaCliente from '../../Components/BuscaCliente.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';

const props = defineProps({
    cobranca: { type: Object, default: null },
    temClientes: { type: Boolean, required: true },
    clienteAtual: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const hoje = new Date().toLocaleDateString('sv-SE');

const form = useForm(props.cobranca ?? {
    cliente_id: '',
    descricao: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    valor: '',
    periodo_inicio: hoje,
    periodo_fim: hoje,
    vencimento: hoje,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
    linha_digitavel: '',
});

const travado = Boolean(props.cobranca?.de_contrato);

const salvar = () => (props.cobranca ? form.put(`/cobrancas/${props.cobranca.id}`) : form.post('/cobrancas'));
</script>

<template>
    <CamadaApp>
        <Link href="/cobrancas" class="font-estreita text-sm text-carbono underline">Voltar para cobranças</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">{{ cobranca ? 'Editar cobrança' : 'Nova cobrança avulsa' }}</h1>
        <p class="mb-6 text-sm text-grafite/80">
            {{ cobranca ? 'O recibo mantém o número e é refeito com os dados novos.' : 'O recibo é emitido junto, com o modelo do cliente.' }}
        </p>

        <p v-if="!temClientes" class="border border-linha bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>

        <form v-else class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <BuscaCliente id="cliente_id" v-model="form.cliente_id" :inicial="clienteAtual" :desativado="travado" obrigatorio />
            </Campo>
            <p v-if="travado" class="font-estreita text-xs text-grafite/70 sm:col-span-6">Cliente e período vêm do contrato.</p>
            <Campo id="descricao" rotulo="Descrição dos serviços (até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="5" class="entrada" required />
            </Campo>
            <Campo id="codigo_servico" rotulo="Serviço prestado" :erro="form.errors.codigo_servico" class="sm:col-span-6">
                <input id="codigo_servico" v-model="form.codigo_servico" class="entrada">
            </Campo>
            <Campo id="codigo_nbs" rotulo="Código NBS" :erro="form.errors.codigo_nbs" class="sm:col-span-3">
                <input id="codigo_nbs" v-model="form.codigo_nbs" class="entrada">
            </Campo>
            <Campo id="codigo_tributacao_nacional" rotulo="Código de tributação nacional" :erro="form.errors.codigo_tributacao_nacional" class="sm:col-span-3">
                <input id="codigo_tributacao_nacional" v-model="form.codigo_tributacao_nacional" class="entrada">
            </Campo>
            <Campo id="valor" rotulo="Valor (R$)" :erro="form.errors.valor" class="sm:col-span-2">
                <input id="valor" v-mascara="'valor'" v-model="form.valor" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="vencimento" rotulo="Vencimento" :erro="form.errors.vencimento" class="sm:col-span-4">
                <input id="vencimento" v-model="form.vencimento" type="date" class="entrada" required>
            </Campo>
            <Campo id="periodo_inicio" rotulo="Início do período" :erro="form.errors.periodo_inicio" class="sm:col-span-3">
                <input id="periodo_inicio" v-model="form.periodo_inicio" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <Campo id="periodo_fim" rotulo="Fim do período" :erro="form.errors.periodo_fim" class="sm:col-span-3">
                <input id="periodo_fim" v-model="form.periodo_fim" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" com-linha-digitavel />
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ cobranca ? 'Salvar cobrança' : 'Criar cobrança e recibo' }}</button>
            </div>
        </form>
    </CamadaApp>
</template>
