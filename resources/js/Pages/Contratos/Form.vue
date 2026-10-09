<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    contrato: { type: Object, default: null },
    clientes: { type: Array, required: true },
    periodicidades: { type: Array, required: true },
});

const form = useForm(props.contrato ?? {
    cliente_id: props.clientes[0]?.id ?? '',
    descricao: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    valor: '',
    periodicidade: 'mensal',
    dia_vencimento: 10,
    data_inicio: new Date().toLocaleDateString('sv-SE'),
    data_fim: '',
    ativo: true,
});

const salvar = () => (props.contrato ? form.put(`/contratos/${props.contrato.id}`) : form.post('/contratos'));
</script>

<template>
    <CamadaApp>
        <Link href="/contratos" class="font-estreita text-sm text-carbono underline">Voltar para contratos</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ contrato ? 'Editar contrato' : 'Novo contrato' }}</h1>

        <p v-if="!clientes.length" class="border border-linha bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>

        <form v-else class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <select id="cliente_id" v-model="form.cliente_id" class="entrada" required>
                    <option v-for="cliente in clientes" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
                </select>
            </Campo>
            <Campo id="descricao" rotulo="Descrição dos serviços (como sai no recibo, até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="6" class="entrada" required />
            </Campo>
            <Campo id="codigo_servico" rotulo="Serviço prestado (ex.: 0102 - Programação)" :erro="form.errors.codigo_servico" class="sm:col-span-6">
                <input id="codigo_servico" v-model="form.codigo_servico" class="entrada">
            </Campo>
            <Campo id="codigo_nbs" rotulo="Código NBS" :erro="form.errors.codigo_nbs" class="sm:col-span-3">
                <input id="codigo_nbs" v-model="form.codigo_nbs" class="entrada">
            </Campo>
            <Campo id="codigo_tributacao_nacional" rotulo="Código de tributação nacional" :erro="form.errors.codigo_tributacao_nacional" class="sm:col-span-3">
                <input id="codigo_tributacao_nacional" v-model="form.codigo_tributacao_nacional" class="entrada">
            </Campo>
            <Campo id="valor" rotulo="Valor (R$)" :erro="form.errors.valor" class="sm:col-span-2">
                <input id="valor" v-model="form.valor" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="periodicidade" rotulo="Periodicidade" :erro="form.errors.periodicidade" class="sm:col-span-2">
                <select id="periodicidade" v-model="form.periodicidade" class="entrada">
                    <option v-for="p in periodicidades" :key="p.valor" :value="p.valor">{{ p.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="dia_vencimento" rotulo="Dia do vencimento" :erro="form.errors.dia_vencimento" class="sm:col-span-2">
                <input id="dia_vencimento" v-model="form.dia_vencimento" type="number" min="1" max="31" class="entrada" required>
            </Campo>
            <Campo id="data_inicio" rotulo="Início do primeiro período" :erro="form.errors.data_inicio" class="sm:col-span-3">
                <input id="data_inicio" v-model="form.data_inicio" type="date" class="entrada" required>
            </Campo>
            <Campo id="data_fim" rotulo="Fim do contrato (opcional)" :erro="form.errors.data_fim" class="sm:col-span-3">
                <input id="data_fim" v-model="form.data_fim" type="date" class="entrada">
            </Campo>
            <label class="flex items-center gap-2 text-sm sm:col-span-6">
                <input v-model="form.ativo" type="checkbox"> Contrato ativo (desmarque para encerrar)
            </label>
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">Salvar contrato</button>
            </div>
        </form>
    </CamadaApp>
</template>
