<script setup>
import { computed, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MAXIMO_EM_CENTAVOS } from '../condicoes.js';
import { menorValor } from '../desconto.js';
import { formatarCentavos } from '../itens.js';
import { camposDoForm, dataDaBaixaEmLote, previaDoLote } from '../usarValoresDaBaixa.js';
import CampoValorOuPercentual from './CampoValorOuPercentual.vue';
import Modal from './Modal.vue';

// `cobrancas`: linhas selecionadas na tela (id, cliente, vencimento, vencimento_iso, valor_centavos, regras, situacao). Previstas/pagas/canceladas
// não têm o que baixar: a prévia já separa isso, sem exigir que a seleção em si seja "limpa" primeiro.
const props = defineProps({ cobrancas: { type: Array, required: true } });
const emit = defineEmits(['fechar', 'sucesso']);

const PENDENTE = ['vencida', 'vence_hoje', 'a_vencer'];
const elegiveis = computed(() => props.cobrancas.filter((c) => PENDENTE.includes(c.situacao)));
const foraDoLote = computed(() => props.cobrancas.length - elegiveis.value.length);
const aindaNaoVenceram = computed(() => elegiveis.value.filter((c) => c.situacao === 'a_vencer').length);

const id = useId();
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
// Campo em branco ('') é diferente de 0: em branco cada cobrança usa o próprio contrato, preenchido (inclusive 0) vale para todas.
const form = useForm({
    ids: [],
    modo: 'vencimento',
    desconto_tipo: 'percentual',
    desconto: '',
    multa_tipo: 'percentual',
    multa: '',
    juros_tipo: 'percentual',
    juros: '',
    outros: '',
});

// Valor, acréscimos, desconto e valor pago de cada cobrança, e os totais: a mesma conta que o servidor grava, na data de cada uma.
const previa = computed(() => previaDoLote(
    elegiveis.value.map((c) => ({ valorCentavos: c.valor_centavos, vencimentoIso: c.vencimento_iso, regras: c.regras ?? null })),
    elegiveis.value.map((c) => dataDaBaixaEmLote(form.modo, c.vencimento_iso, hoje)),
    camposDoForm(form),
));
// Em R$ o desconto não passa da menor cobrança (nenhuma fica negativa).
const menorDasCobrancas = computed(() => menorValor(elegiveis.value.map((c) => c.valor_centavos)));
const erroDo = (nome) => form.errors[nome] || form.errors[`${nome}_tipo`];
const limparErro = (nome) => form.clearErrors(nome, `${nome}_tipo`);
const AVISO_DO_VENCIMENTO = 'Com essa data não há multa nem juros; o desconto do contrato vale.';

// O @change do CampoValorOuPercentual já tira a vírgula final antes do envio; em branco segue como '' (nunca vira 0).
const confirmar = () => form.transform((dados) => ({ ...dados, ids: elegiveis.value.map((c) => c.id) }))
    .post('/cobrancas/lote/pagar', { preserveScroll: true, onSuccess: () => emit('sucesso') });
</script>

<template>
    <Modal aberto titulo="Dar baixa em lote" @fechar="emit('fechar')">
        <p class="text-sm">
            {{ elegiveis.length }} de {{ cobrancas.length }} selecionada(s) {{ elegiveis.length === 1 ? 'será baixada' : 'serão baixadas' }}
            (R$ {{ formatarCentavos(previa.totais.valorCentavos) }}).
            <span v-if="foraDoLote"> {{ foraDoLote }} ficará(ão) de fora: não {{ foraDoLote === 1 ? 'está' : 'estão' }} pendente(s).</span>
        </p>
        <fieldset class="mt-4 grid gap-2">
            <legend class="text-sm font-semibold">Data da baixa</legend>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.modo" type="radio" value="vencimento" class="accent-carbono">
                Usar a data de vencimento de cada cobrança
                <span v-if="aindaNaoVenceram" class="text-xs text-grafite/70">
                    ({{ aindaNaoVenceram }} ainda não venceu{{ aindaNaoVenceram === 1 ? '' : 'ram' }}: usa hoje nessa(s))
                </span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.modo" type="radio" value="hoje" class="accent-carbono">
                Usar a data de hoje para todas
            </label>
            <Transition name="surgir">
                <p v-if="form.modo === 'vencimento'" aria-hidden="true" class="text-xs text-grafite/80">{{ AVISO_DO_VENCIMENTO }}</p>
            </Transition>
            <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
            <p class="sr-only" aria-live="polite">{{ form.modo === 'vencimento' ? AVISO_DO_VENCIMENTO : '' }}</p>
        </fieldset>
        <fieldset class="mt-4 min-w-0" :aria-describedby="`${id}-ajuda`">
            <legend class="text-sm font-semibold">Valores para todas</legend>
            <p :id="`${id}-ajuda`" class="mt-1 text-xs text-grafite/80">Em branco: cada cobrança usa o próprio contrato. Preenchido: vale para todas.</p>
            <div class="mt-2 grid gap-x-4 gap-y-3 sm:grid-cols-2">
                <CampoValorOuPercentual
                    :id="`${id}-multa`"
                    v-model="form.multa"
                    v-model:tipo="form.multa_tipo"
                    rotulo="Multa"
                    :erro="erroDo('multa')"
                    ajuda="Cobrada uma vez, só nas cobranças em atraso. Digite % ou R$."
                    :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                    @digitar="limparErro('multa')"
                />
                <CampoValorOuPercentual
                    :id="`${id}-juros`"
                    v-model="form.juros"
                    v-model:tipo="form.juros_tipo"
                    rotulo="Juros ao mês"
                    :erro="erroDo('juros')"
                    ajuda="Juros simples, proporcionais aos dias de atraso. Digite % ou R$."
                    :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                    @digitar="limparErro('juros')"
                />
                <CampoValorOuPercentual
                    :id="`${id}-outros`"
                    v-model="form.outros"
                    rotulo="Outros acréscimos"
                    :erro="erroDo('outros')"
                    ajuda="Outros valores a cobrar, só em R$, nas cobranças em atraso."
                    :valor-maximo-centavos="MAXIMO_EM_CENTAVOS"
                    somente-reais
                    @digitar="limparErro('outros')"
                />
                <CampoValorOuPercentual
                    :id="`${id}-desconto`"
                    v-model="form.desconto"
                    v-model:tipo="form.desconto_tipo"
                    rotulo="Desconto"
                    :erro="erroDo('desconto')"
                    ajuda="Digite % ou R$. Em R$, o mesmo valor sai de cada cobrança."
                    :valor-maximo-centavos="menorDasCobrancas"
                    complemento-do-maximo=" (valor da menor cobrança)"
                    @digitar="limparErro('desconto')"
                />
            </div>
        </fieldset>
        <div v-if="elegiveis.length" class="relative mt-4 max-h-64 overflow-auto border border-linha">
            <table class="w-full text-xs tabular-nums">
                <caption class="sr-only">Prévia da baixa: vencimento, valor, acréscimos, desconto e valor pago de cada cobrança, em reais</caption>
                <thead class="sticky top-0 bg-white font-estreita text-grafite/80">
                    <tr class="border-b border-linha text-right">
                        <th scope="col" class="px-2 py-1 text-left font-normal">Cobrança</th>
                        <th scope="col" class="whitespace-nowrap px-2 py-1 font-normal">Vencimento</th>
                        <th scope="col" class="px-2 py-1 font-normal">Valor</th>
                        <th scope="col" class="px-2 py-1 font-normal">Acréscimos</th>
                        <th scope="col" class="px-2 py-1 font-normal">Desconto</th>
                        <th scope="col" class="px-2 py-1 font-normal">Pago</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(cobranca, i) in elegiveis" :key="cobranca.id" class="border-b border-linha/60 text-right">
                        <td class="w-full max-w-0 px-2 py-1 text-left"><p class="truncate">{{ cobranca.cliente }}</p></td>
                        <td class="whitespace-nowrap px-2 py-1">{{ cobranca.vencimento }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].valorCentavos) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].acrescimosCentavos) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].descontoCentavos) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].pagoCentavos) }}</td>
                    </tr>
                </tbody>
                <tfoot class="font-semibold">
                    <tr class="text-right">
                        <th scope="row" colspan="2" class="px-2 py-1 text-left">Total</th>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.totais.valorCentavos) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.totais.acrescimosCentavos) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.totais.descontoCentavos) }}</td>
                        <td class="px-2 py-1"><span :key="previa.totais.pagoCentavos" class="destacar inline-block">{{ formatarCentavos(previa.totais.pagoCentavos) }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p v-if="form.errors.ids" class="mt-2 text-xs text-tinta-vermelha">{{ form.errors.ids }}</p>
        <div class="mt-4 flex gap-2">
            <button type="button" class="botao" :disabled="form.processing || !elegiveis.length" @click="confirmar">Confirmar baixa</button>
            <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
        </div>
    </Modal>
</template>
