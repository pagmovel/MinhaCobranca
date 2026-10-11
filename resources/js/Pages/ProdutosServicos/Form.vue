<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    item: { type: Object, default: null },
    tipos: { type: Array, required: true },
});

const form = useForm(props.item ?? {
    nome: '',
    tipo: 'servico',
    unidade: 'mês',
    preco: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    ativo: true,
});

const confirmandoExclusao = ref(false);

const salvar = () => (props.item ? form.put(`/produtos-servicos/${props.item.id}`) : form.post('/produtos-servicos'));
const excluir = () => router.delete(`/produtos-servicos/${props.item.id}`);
</script>

<template>
    <CamadaApp>
        <Link href="/produtos-servicos" class="font-estreita text-sm text-carbono underline">Voltar para produtos e serviços</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ item ? 'Editar produto ou serviço' : 'Novo produto ou serviço' }}</h1>

        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="nome" rotulo="Nome" :erro="form.errors.nome" class="sm:col-span-4">
                <input id="nome" v-model="form.nome" maxlength="120" class="entrada" required>
            </Campo>
            <Campo id="tipo" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="tipo" v-model="form.tipo" class="entrada">
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="preco" rotulo="Preço sugerido (R$)" :erro="form.errors.preco" class="sm:col-span-3">
                <input id="preco" v-model="form.preco" v-mascara="'valor'" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="unidade" rotulo="Unidade (ex.: mês, hora, un)" :erro="form.errors.unidade" class="sm:col-span-3">
                <input id="unidade" v-model="form.unidade" maxlength="20" class="entrada" required>
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
            <label class="flex items-center gap-2 text-sm sm:col-span-6">
                <input v-model="form.ativo" type="checkbox"> Ativo (desmarque para não oferecer mais em contratos e cobranças novos)
            </label>
            <div class="flex flex-wrap gap-3 sm:col-span-6">
                <button class="botao" :disabled="form.processing">Salvar</button>
                <template v-if="item">
                    <Link :href="`/produtos-servicos/${item.id}/reajuste`" class="botao-secundario">Reajustar contratos</Link>
                    <button v-if="!confirmandoExclusao" type="button" class="botao-perigo" @click="confirmandoExclusao = true">Excluir</button>
                    <template v-else>
                        <button type="button" class="botao-perigo-confirmar" @click="excluir">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmandoExclusao = false">Manter</button>
                    </template>
                </template>
            </div>
        </form>
    </CamadaApp>
</template>
