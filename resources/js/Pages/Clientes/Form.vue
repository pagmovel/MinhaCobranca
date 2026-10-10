<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CamposEndereco from '../../Components/CamposEndereco.vue';

const props = defineProps({
    cliente: { type: Object, default: null },
    modeloPadrao: { type: String, required: true },
    modelos: { type: Array, required: true },
    ufs: { type: Array, required: true },
});

const campos = ['nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'];

const form = useForm({
    ...Object.fromEntries(campos.map((campo) => [campo, props.cliente?.[campo] ?? ''])),
    modelo: props.cliente?.modelo ?? props.modeloPadrao,
});

const modeloMudou = computed(() => props.cliente && form.modelo !== props.cliente.modelo);
const confirmandoExclusao = ref(false);

const salvar = () => (props.cliente ? form.put(`/clientes/${props.cliente.id}`) : form.post('/clientes'));
const excluir = () => router.delete(`/clientes/${props.cliente.id}`);
</script>

<template>
    <CamadaApp>
        <Link href="/clientes" class="font-estreita text-sm text-carbono underline">Voltar para clientes</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ cliente ? 'Editar cliente' : 'Novo cliente' }}</h1>

        <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="salvar">
            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Tomador do serviço</legend>
                <Campo id="nome" rotulo="Nome ou razão social" :erro="form.errors.nome" class="sm:col-span-4">
                    <input id="nome" v-model="form.nome" class="entrada" required>
                </Campo>
                <Campo id="documento" rotulo="CPF ou CNPJ" :erro="form.errors.documento" class="sm:col-span-2">
                    <input id="documento" v-mascara="'documento'" v-model="form.documento" class="entrada" required>
                </Campo>
                <Campo id="inscricao_municipal" rotulo="Inscrição municipal" :erro="form.errors.inscricao_municipal" class="sm:col-span-2">
                    <input id="inscricao_municipal" v-model="form.inscricao_municipal" class="entrada">
                </Campo>
                <Campo id="inscricao_estadual" rotulo="Inscrição estadual" :erro="form.errors.inscricao_estadual" class="sm:col-span-2">
                    <input id="inscricao_estadual" v-model="form.inscricao_estadual" class="entrada">
                </Campo>
                <Campo id="telefone" rotulo="Telefone" :erro="form.errors.telefone" class="sm:col-span-2">
                    <input id="telefone" v-mascara="'telefone'" inputmode="tel" v-model="form.telefone" type="tel" class="entrada">
                </Campo>
                <Campo id="email" rotulo="E-mail" :erro="form.errors.email" class="sm:col-span-6">
                    <input id="email" v-model="form.email" type="email" class="entrada">
                </Campo>
            </fieldset>

            <CamposEndereco :form="form" :ufs="ufs" />

            <fieldset class="flex flex-col gap-3">
                <legend class="mb-2 text-sm font-semibold">Modelo do recibo</legend>
                <label v-for="modelo in modelos" :key="modelo.valor" class="flex items-center gap-2 text-sm">
                    <input v-model="form.modelo" type="radio" name="modelo" :value="modelo.valor"> {{ modelo.rotulo }}
                </label>
                <p v-if="form.errors.modelo" class="text-xs text-tinta-vermelha">{{ form.errors.modelo }}</p>
                <p v-if="modeloMudou" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm">
                    Recibos já emitidos para este cliente mantêm o modelo anterior. Só os próximos usam o novo.
                </p>
            </fieldset>

            <div class="flex flex-wrap gap-3">
                <button class="botao" :disabled="form.processing">Salvar cliente</button>
                <template v-if="cliente">
                    <button v-if="!confirmandoExclusao" type="button" class="botao-perigo" @click="confirmandoExclusao = true">Excluir cliente</button>
                    <template v-else>
                        <button type="button" class="botao-perigo-confirmar" @click="excluir">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmandoExclusao = false">Manter cliente</button>
                    </template>
                </template>
            </div>
        </form>
    </CamadaApp>
</template>
