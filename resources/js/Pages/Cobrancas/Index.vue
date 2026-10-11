<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import LinhaCobranca from '../../Components/LinhaCobranca.vue';
import BotaoEnviar from '../../Components/BotaoEnviar.vue';
import BaixaInline from '../../Components/BaixaInline.vue';
import CancelarInline from '../../Components/CancelarInline.vue';
import PagamentoInline from '../../Components/PagamentoInline.vue';

const props = defineProps({
    cobrancas: { type: Array, required: true },
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
// Vencendo hoje e vencidas valem para qualquer mês (o servidor ignora o mês nesses dois).
const todosOsMeses = computed(() => ['vence_hoje', 'vencida'].includes(filtro.status));
const vazio = computed(() => ({
    vence_hoje: 'Nenhuma cobrança vence hoje.',
    vencida: 'Nenhuma cobrança vencida em aberto.',
}[props.filtros.status] ?? 'Nenhuma cobrança com vencimento neste mês e filtros.'));

const filtrar = () => router.get('/cobrancas', filtro, { preserveState: true, replace: true });
// Escolher um mês é pedir para ver aquele mês: sai de "Vencendo hoje"/"Vencidas", que buscam em todos os meses.
function escolheuMes() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrar();
}
const aberta = (cobranca) => ['a_vencer', 'vence_hoje', 'vencida'].includes(cobranca.situacao);
const rotuloEnvio = (cobranca) => (cobranca.situacao === 'paga' ? 'Enviar recibo pago' : cobranca.enviado ? 'Reenviar' : 'Enviar');
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Cobranças</h1>
            <Link href="/cobrancas/create" class="botao">Nova cobrança avulsa</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="filtrar">
            <Campo id="mes" :rotulo="todosOsMeses ? 'Mês do vencimento (buscando em todos os meses)' : 'Mês do vencimento'">
                <input id="mes" v-model="filtro.mes" type="month" class="entrada" @change="escolheuMes">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option value="vence_hoje">Vencendo hoje</option>
                    <option value="vencida">Vencidas</option>
                    <option value="a_vencer">A vencer</option>
                    <option value="pendente">Pendentes (todas em aberto)</option>
                    <option value="paga">Pagas</option>
                    <option value="cancelada">Canceladas</option>
                </select>
            </Campo>
            <Campo id="cliente_id" rotulo="Cliente">
                <BuscaAutosugestao id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />
            </Campo>
            <div class="flex items-end">
                <button class="botao-secundario w-full">Filtrar</button>
            </div>
        </form>

        <p v-if="!cobrancas.length" class="border border-carbono bg-white px-4 py-3 text-sm">
            {{ vazio }}
        </p>

        <TransitionGroup v-else tag="ul" name="lista" class="relative divide-y divide-linha border border-linha bg-white">
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
                <PagamentoInline
                    v-if="aberta(cobranca)"
                    :cobranca-id="cobranca.id"
                    :pagamento="cobranca.pagamento"
                    :chaves-pix="chavesPix"
                    :formas-pagamento="formasPagamento"
                />
                <Link v-if="cobranca.editavel" :href="`/cobrancas/${cobranca.id}/edit`" class="botao-secundario">Editar</Link>
                <CancelarInline v-if="aberta(cobranca)" :cobranca-id="cobranca.id" />
            </LinhaCobranca>
        </TransitionGroup>
    </CamadaApp>
</template>
