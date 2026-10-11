<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    item: { type: Object, required: true },
    contratos: { type: Array, required: true },
});

const form = useForm({ contratos: props.contratos.map((contrato) => contrato.id), preco: props.item.preco });

const salvar = () => form.post(`/produtos-servicos/${props.item.id}/reajuste`);
</script>

<template>
    <CamadaApp>
        <Link :href="`/produtos-servicos/${item.id}/edit`" class="font-estreita text-sm text-carbono underline">Voltar para {{ item.nome }}</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">Reajustar contratos</h1>
        <p class="mb-6 max-w-3xl text-sm text-grafite/80">
            {{ item.nome }}: preço do catálogo R$ {{ item.preco }} por {{ item.unidade }}. O novo preço vale para as
            próximas cobranças dos contratos marcados; as cobranças já geradas não mudam.
        </p>

        <p v-if="!contratos.length" class="border border-linha bg-white px-4 py-3 text-sm">Nenhum contrato ativo usa este item.</p>

        <form v-else class="flex max-w-3xl flex-col gap-6" @submit.prevent="salvar">
            <fieldset class="border border-linha bg-white">
                <legend class="sr-only">Contratos ativos que usam este item</legend>
                <label
                    v-for="contrato in contratos"
                    :key="contrato.id"
                    class="flex items-start gap-3 border-b border-linha px-4 py-3 text-sm last:border-b-0"
                >
                    <input v-model="form.contratos" type="checkbox" :value="contrato.id" class="mt-1">
                    <span>
                        <span class="font-semibold">{{ contrato.cliente }}</span>, {{ contrato.resumo }}
                        <span class="block font-estreita text-xs text-grafite/70">{{ contrato.quantidade }} × R$ {{ contrato.preco }} hoje</span>
                    </span>
                </label>
            </fieldset>
            <p v-if="form.errors.contratos" class="text-xs text-tinta-vermelha">{{ form.errors.contratos }}</p>
            <Campo id="preco" rotulo="Novo preço unitário (R$)" :erro="form.errors.preco" class="max-w-xs">
                <input id="preco" v-model="form.preco" v-mascara="'valor'" inputmode="decimal" class="entrada" required>
            </Campo>
            <div>
                <button class="botao" :disabled="form.processing || !form.contratos.length">Aplicar o novo preço</button>
            </div>
        </form>
    </CamadaApp>
</template>
