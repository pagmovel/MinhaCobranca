<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CampoCliente from '../../Components/CampoCliente.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';
import SecaoItens from '../../Components/SecaoItens.vue';

const props = defineProps({
    contrato: { type: Object, default: null },
    clienteAtual: { type: Object, default: null },
    periodicidades: { type: Array, required: true },
    modalidades: { type: Array, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    cadastroRapido: { type: Object, required: true },
});

const form = useForm(props.contrato ?? {
    cliente_id: '',
    descricao: '',
    itens: [],
    periodicidade: 'mensal',
    modalidade: 'prepago',
    dia_vencimento: 10,
    data_inicio: new Date().toLocaleDateString('sv-SE'),
    data_fim: '',
    ativo: true,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
});

const semItens = Boolean(props.contrato && !props.contrato.itens.length);

const salvar = () => (props.contrato ? form.put(`/contratos/${props.contrato.id}`) : form.post('/contratos'));
</script>

<template>
    <CamadaApp>
        <Link href="/contratos" class="font-estreita text-sm text-carbono underline">Voltar para contratos</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ contrato ? 'Editar contrato' : 'Novo contrato' }}</h1>


        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <CampoCliente :form="form" :inicial="clienteAtual" :opcoes="cadastroRapido" />
            </Campo>
            <Campo id="descricao" rotulo="Descrição dos serviços (como sai no recibo, até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="6" class="entrada" required />
            </Campo>
            <p v-if="semItens" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                Este contrato ainda não tem itens (valor atual: R$ {{ contrato.valor }}). Inclua pelo menos um; ao salvar, o
                valor passa a ser a soma dos itens.
            </p>
            <SecaoItens :form="form" />
            <Campo id="periodicidade" rotulo="Periodicidade" :erro="form.errors.periodicidade" class="sm:col-span-3">
                <select id="periodicidade" v-model="form.periodicidade" class="entrada">
                    <option v-for="p in periodicidades" :key="p.valor" :value="p.valor">{{ p.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="dia_vencimento" rotulo="Dia do vencimento" :erro="form.errors.dia_vencimento" class="sm:col-span-3">
                <input id="dia_vencimento" v-model="form.dia_vencimento" type="number" min="1" max="31" class="entrada" required>
            </Campo>
            <fieldset class="sm:col-span-6">
                <legend class="mb-2 font-estreita text-xs font-medium text-grafite/75">Modalidade</legend>
                <div class="flex flex-wrap gap-4">
                    <label v-for="modalidade in modalidades" :key="modalidade.valor" class="flex items-center gap-2 text-sm">
                        <input v-model="form.modalidade" type="radio" name="modalidade" :value="modalidade.valor"> {{ modalidade.rotulo }}
                    </label>
                </div>
                <p class="mt-1 text-xs text-grafite/70">
                    {{ form.modalidade === 'pospago'
                        ? 'Vence no dia escolhido depois que o período termina (serviço de outubro vence em novembro).'
                        : 'Vence no dia escolhido do mês em que o período começa.' }}
                </p>
                <p v-if="form.errors.modalidade" class="text-xs text-tinta-vermelha">{{ form.errors.modalidade }}</p>
            </fieldset>
            <Campo id="data_inicio" rotulo="Início do primeiro período" :erro="form.errors.data_inicio" class="sm:col-span-3">
                <input id="data_inicio" v-model="form.data_inicio" type="date" class="entrada" required>
            </Campo>
            <Campo id="data_fim" rotulo="Fim do contrato (opcional)" :erro="form.errors.data_fim" class="sm:col-span-3">
                <input id="data_fim" v-model="form.data_fim" type="date" class="entrada">
            </Campo>
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            <label class="flex items-center gap-2 text-sm sm:col-span-6">
                <input v-model="form.ativo" type="checkbox"> Contrato ativo (desmarque para encerrar)
            </label>
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">Salvar contrato</button>
            </div>
        </form>
    </CamadaApp>
</template>
