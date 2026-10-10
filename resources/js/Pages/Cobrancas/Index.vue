<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import LinhaCobranca from '../../Components/LinhaCobranca.vue';
import BotaoEnviar from '../../Components/BotaoEnviar.vue';
import BaixaInline from '../../Components/BaixaInline.vue';
import CancelarInline from '../../Components/CancelarInline.vue';

const props = defineProps({
    cobrancas: { type: Array, required: true },
    filtros: { type: Object, required: true },
    clientes: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });

const filtrar = () => router.get('/cobrancas', filtro, { preserveState: true, replace: true });
const aberta = (cobranca) => cobranca.situacao === 'a_vencer' || cobranca.situacao === 'vencida';
const rotuloEnvio = (cobranca) => (cobranca.situacao === 'paga' ? 'Enviar recibo pago' : cobranca.enviado ? 'Reenviar' : 'Enviar');
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Cobranças</h1>
            <Link href="/cobrancas/create" class="botao">Nova cobrança avulsa</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="filtrar">
            <Campo id="mes" rotulo="Mês do vencimento">
                <input id="mes" v-model="filtro.mes" type="month" class="entrada">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada">
                    <option value="">Todas</option>
                    <option value="pendente">Pendentes</option>
                    <option value="paga">Pagas</option>
                    <option value="cancelada">Canceladas</option>
                </select>
            </Campo>
            <Campo id="cliente_id" rotulo="Cliente">
                <select id="cliente_id" v-model="filtro.cliente_id" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="cliente in clientes" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
                </select>
            </Campo>
            <div class="flex items-end">
                <button class="botao-secundario w-full">Filtrar</button>
            </div>
        </form>

        <p v-if="!cobrancas.length" class="border border-carbono bg-white px-4 py-3 text-sm">
            Nenhuma cobrança com vencimento neste mês e filtros.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <LinhaCobranca v-for="cobranca in cobrancas" :key="cobranca.id" :cobranca="cobranca">
                <a v-if="cobranca.recibo" :href="cobranca.recibo.pdf_url" target="_blank" rel="noopener" class="botao-secundario">Ver PDF</a>
                <BotaoEnviar
                    v-if="cobranca.recibo && cobranca.situacao !== 'cancelada'"
                    :rotulo="rotuloEnvio(cobranca)"
                    :cobranca-id="cobranca.id"
                    :pdf-url="cobranca.recibo.pdf_url"
                    :nome-arquivo="cobranca.recibo.pdf_nome"
                />
                <BaixaInline v-if="aberta(cobranca)" :cobranca-id="cobranca.id" />
                <Link v-if="cobranca.editavel" :href="`/cobrancas/${cobranca.id}/edit`" class="botao-secundario">Editar</Link>
                <CancelarInline v-if="aberta(cobranca)" :cobranca-id="cobranca.id" />
            </LinhaCobranca>
        </ul>
    </CamadaApp>
</template>
