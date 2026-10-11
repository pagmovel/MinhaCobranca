<script setup>
import { useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaCnpj from '../../Components/BuscaCnpj.vue';
import CamposEndereco from '../../Components/CamposEndereco.vue';
import ChavesPix from '../../Components/ChavesPix.vue';
import Feriados from '../../Components/Feriados.vue';
import MensagensWhatsapp from '../../Components/MensagensWhatsapp.vue';

const props = defineProps({
    perfil: { type: Object, required: true },
    modelos: { type: Array, required: true },
    ufs: { type: Array, required: true },
    chavesPix: { type: Array, required: true },
    tiposChavePix: { type: Array, required: true },
    feriados: { type: Array, required: true },
    feriadosNacionais: { type: Array, required: true },
    vencimentosParaAjustar: { type: Array, required: true },
    modelosMensagem: { type: Array, required: true },
});

const form = useForm({ ...props.perfil, logo: null });

const salvar = () => form.post('/perfil', { preserveScroll: true });
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">Meu perfil</h1>
        <p class="mt-1 mb-6 text-sm text-grafite/80">Estes dados aparecem como prestador em todos os recibos novos.</p>

        <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="salvar">
            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Prestador</legend>
                <Campo id="name" rotulo="Nome ou razão social" :erro="form.errors.name" class="sm:col-span-4">
                    <input id="name" v-model="form.name" class="entrada" required>
                </Campo>
                <Campo id="documento" rotulo="CPF ou CNPJ" :erro="form.errors.documento" class="sm:col-span-2">
                    <input id="documento" v-mascara="'documento'" v-model="form.documento" class="entrada" required>
                </Campo>
                <BuscaCnpj :form="form" campo-nome="name" />
                <Campo id="nome_fantasia" rotulo="Nome fantasia" :erro="form.errors.nome_fantasia" class="sm:col-span-3">
                    <input id="nome_fantasia" v-model="form.nome_fantasia" class="entrada">
                </Campo>
                <Campo id="telefone" rotulo="Telefone" :erro="form.errors.telefone" class="sm:col-span-3">
                    <input id="telefone" v-mascara="'telefone'" v-model="form.telefone" type="tel" autocomplete="tel" class="entrada" required>
                </Campo>
                <Campo id="inscricao_municipal" rotulo="Inscrição municipal" :erro="form.errors.inscricao_municipal" class="sm:col-span-3">
                    <input id="inscricao_municipal" v-model="form.inscricao_municipal" class="entrada">
                </Campo>
                <Campo id="inscricao_estadual" rotulo="Inscrição estadual" :erro="form.errors.inscricao_estadual" class="sm:col-span-3">
                    <input id="inscricao_estadual" v-model="form.inscricao_estadual" class="entrada">
                </Campo>
                <p class="font-estreita text-xs text-grafite/70 sm:col-span-6">E-mail nos recibos: {{ perfil.email }}</p>
            </fieldset>

            <CamposEndereco :form="form" :ufs="ufs" obrigatorio />

            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Recibos</legend>
                <Campo id="modelo_padrao" rotulo="Modelo sugerido para clientes novos" :erro="form.errors.modelo_padrao" class="sm:col-span-3">
                    <select id="modelo_padrao" v-model="form.modelo_padrao" class="entrada">
                        <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                    </select>
                </Campo>
                <Campo id="dias_antecedencia" rotulo="Gerar a cobrança quantos dias antes do vencimento" :erro="form.errors.dias_antecedencia" class="sm:col-span-3">
                    <input id="dias_antecedencia" v-model="form.dias_antecedencia" type="number" min="0" max="60" class="entrada">
                </Campo>
                <Campo id="dias_aviso" rotulo="Avisar sobre vencimento com quantos dias de antecedência" :erro="form.errors.dias_aviso" class="sm:col-span-3">
                    <input id="dias_aviso" v-model="form.dias_aviso" type="number" min="1" max="30" class="entrada">
                </Campo>
                <Campo id="logo" rotulo="Logo nos recibos (PNG ou JPG, até 1 MB)" :erro="form.errors.logo" class="sm:col-span-6">
                    <img v-if="perfil.tem_logo" src="/perfil/logo" alt="Logo atual" class="mb-2 h-16 w-auto">
                    <input id="logo" type="file" accept="image/png,image/jpeg" @input="form.logo = $event.target.files[0]">
                </Campo>
            </fieldset>

            <div>
                <button class="botao" :disabled="form.processing">Salvar perfil</button>
            </div>
        </form>

        <ChavesPix class="mt-10" :chaves="chavesPix" :tipos="tiposChavePix" />
        <Feriados class="mt-10" :feriados="feriados" :nacionais="feriadosNacionais" :vencimentos="vencimentosParaAjustar" />
        <MensagensWhatsapp class="mt-10" :modelos="modelosMensagem" :prestador="form.nome_fantasia || form.name" />
    </CamadaApp>
</template>
