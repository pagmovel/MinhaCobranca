<script setup>
import { ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { buscarJson } from '../../http.js';
import { criarAvisoDeDiaUtil } from '../../avisoDiaUtil.js';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CampoCliente from '../../Components/CampoCliente.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';
import DescricaoComplementar from '../../Components/DescricaoComplementar.vue';
import SecaoItens from '../../Components/SecaoItens.vue';

const props = defineProps({
    cobranca: { type: Object, default: null },
    clienteAtual: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    cadastroRapido: { type: Object, required: true },
});

// Cliente escolhido (formato da autosugestão): a descrição oferece incluir o apelido.
const cliente = ref(props.clienteAtual);

const hoje = new Date().toLocaleDateString('sv-SE');

const form = useForm(props.cobranca ?? {
    cliente_id: '',
    descricao: '',
    itens: [],
    periodo_inicio: hoje,
    periodo_fim: hoje,
    vencimento: hoje,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
    linha_digitavel: '',
});

// Vencimento em dia não útil (Fase 23): a data digitada vale; o aviso só sugere o próximo dia útil (regra no servidor).
const diaUtil = ref(null);
const conferirVencimento = criarAvisoDeDiaUtil(
    (data) => buscarJson(`/dias-uteis/proximo?data=${encodeURIComponent(data)}`),
    (aviso) => { diaUtil.value = aviso; },
);
watch(() => form.vencimento, conferirVencimento, { immediate: true });

const travado = Boolean(props.cobranca?.de_contrato);
const semItens = Boolean(props.cobranca && !props.cobranca.itens.length);

const salvar = () => (props.cobranca ? form.put(`/cobrancas/${props.cobranca.id}`) : form.post('/cobrancas'));
</script>

<template>
    <CamadaApp>
        <Link href="/cobrancas" class="font-estreita text-sm text-carbono underline">Voltar para cobranças</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">{{ cobranca ? 'Editar cobrança' : 'Nova cobrança avulsa' }}</h1>
        <p class="mb-6 text-sm text-grafite/80">
            {{ cobranca ? 'O recibo mantém o número e é refeito com os dados novos.' : 'O recibo é emitido junto, com o modelo do cliente.' }}
        </p>


        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <CampoCliente :form="form" :inicial="clienteAtual" :desativado="travado" :opcoes="cadastroRapido" @escolhido="cliente = $event" />
            </Campo>
            <p v-if="travado" class="font-estreita text-xs text-grafite/70 sm:col-span-6">Cliente e período vêm do contrato.</p>
            <p v-if="semItens" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                Esta cobrança é anterior ao catálogo (valor atual: R$ {{ cobranca.valor }}). Inclua pelo menos um item; ao salvar,
                o valor passa a ser a soma dos itens.
            </p>
            <SecaoItens :form="form" />
            <DescricaoComplementar :form="form" :cliente="cliente" />
            <Campo id="vencimento" rotulo="Vencimento" :erro="form.errors.vencimento" class="sm:col-span-2">
                <input id="vencimento" v-model="form.vencimento" type="date" class="entrada" required>
            </Campo>
            <Campo id="periodo_inicio" rotulo="Início do período" :erro="form.errors.periodo_inicio" class="sm:col-span-2">
                <input id="periodo_inicio" v-model="form.periodo_inicio" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <Campo id="periodo_fim" rotulo="Fim do período" :erro="form.errors.periodo_fim" class="sm:col-span-2">
                <input id="periodo_fim" v-model="form.periodo_fim" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <!-- Região viva sempre presente: o leitor de tela só anuncia mudança numa região que já existia. -->
            <p class="sr-only" aria-live="polite">{{ diaUtil ? `${diaUtil.aviso} Próximo dia útil: ${diaUtil.proximo_rotulo}.` : '' }}</p>
            <Transition name="surgir">
                <p v-if="diaUtil" class="flex flex-wrap items-center gap-2 border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                    <span>{{ diaUtil.aviso }} Próximo dia útil: {{ diaUtil.proximo_rotulo }}.</span>
                    <button type="button" class="botao-secundario" @click="form.vencimento = diaUtil.proximo">Usar {{ diaUtil.proximo_rotulo }}</button>
                </p>
            </Transition>
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" com-linha-digitavel />
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ cobranca ? 'Salvar cobrança' : 'Criar cobrança e recibo' }}</button>
            </div>
        </form>
    </CamadaApp>
</template>
