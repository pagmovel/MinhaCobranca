<script setup>
import { computed, ref, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { menorValor, previaDoLote, semSeparadorFinal } from '../desconto.js';
import { formatarCentavos } from '../itens.js';
import { usarDescontoDaBaixa } from '../usarDescontoDaBaixa.js';
import Modal from './Modal.vue';

// `cobrancas`: linhas selecionadas na tela (id, cliente, vencimento, valor_centavos, situacao). Previstas/pagas/canceladas
// não têm o que baixar: a prévia já separa isso, sem exigir que a seleção em si seja "limpa" primeiro.
const props = defineProps({ cobrancas: { type: Array, required: true } });
const emit = defineEmits(['fechar', 'sucesso']);

const PENDENTE = ['vencida', 'vence_hoje', 'a_vencer'];
const elegiveis = computed(() => props.cobrancas.filter((c) => PENDENTE.includes(c.situacao)));
const foraDoLote = computed(() => props.cobrancas.length - elegiveis.value.length);
const aindaNaoVenceram = computed(() => elegiveis.value.filter((c) => c.situacao === 'a_vencer').length);

const idDesconto = useId();
const modo = ref('vencimento');
const form = useForm({ ids: [], modo: 'vencimento', desconto_tipo: 'percentual', desconto: '' });

const valores = computed(() => elegiveis.value.map((c) => c.valor_centavos));
// Valor, desconto e valor pago de cada cobrança, e os totais: a mesma conta que o servidor grava.
const previa = computed(() => previaDoLote(valores.value, form.desconto_tipo, form.desconto));

// Mesma lógica do seletor da baixa individual; no lote o limite em R$ é a menor cobrança (nenhuma fica negativa).
const { emReais, aviso, erroDoDesconto } = usarDescontoDaBaixa(form, () => menorValor(valores.value), { complementoDoMaximo: ' (valor da menor cobrança)' });

const confirmar = () => form.transform((dados) => ({ ...dados, ids: elegiveis.value.map((c) => c.id), modo: modo.value, desconto: semSeparadorFinal(dados.desconto) }))
    .post('/cobrancas/lote/pagar', { preserveScroll: true, onSuccess: () => emit('sucesso') });
</script>

<template>
    <Modal aberto titulo="Dar baixa em lote" @fechar="emit('fechar')">
        <p class="text-sm">
            {{ elegiveis.length }} de {{ cobrancas.length }} selecionada(s) {{ elegiveis.length === 1 ? 'será baixada' : 'serão baixadas' }}
            (R$ {{ formatarCentavos(previa.totais.valor) }}).
            <span v-if="foraDoLote"> {{ foraDoLote }} ficará(ão) de fora: não {{ foraDoLote === 1 ? 'está' : 'estão' }} pendente(s).</span>
        </p>
        <fieldset class="mt-4 grid gap-2">
            <legend class="text-sm font-semibold">Data da baixa</legend>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="modo" type="radio" value="vencimento" class="accent-carbono">
                Usar a data de vencimento de cada cobrança
                <span v-if="aindaNaoVenceram" class="text-xs text-grafite/70">
                    ({{ aindaNaoVenceram }} ainda não venceu{{ aindaNaoVenceram === 1 ? '' : 'ram' }}: usa hoje nessa(s))
                </span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="modo" type="radio" value="hoje" class="accent-carbono">
                Usar a data de hoje para todas
            </label>
        </fieldset>
        <div class="mt-4 flex flex-col font-estreita text-xs">
            <label :for="idDesconto">Desconto para todas</label>
            <div class="flex gap-1">
                <input
                    :id="idDesconto"
                    v-model="form.desconto"
                    v-mascara="emReais ? 'valor' : 'percentual'"
                    inputmode="decimal"
                    autocomplete="off"
                    :placeholder="emReais ? '0,00' : '0'"
                    class="entrada w-24"
                    :aria-invalid="erroDoDesconto ? 'true' : undefined"
                    :aria-describedby="erroDoDesconto ? `${idDesconto}-ajuda ${idDesconto}-erro` : `${idDesconto}-ajuda`"
                >
                <select v-model="form.desconto_tipo" class="entrada w-auto" aria-label="Tipo do desconto: percentual ou reais">
                    <option value="percentual">%</option>
                    <option value="valor">R$</option>
                </select>
            </div>
            <p :id="`${idDesconto}-ajuda`" class="mt-1 text-xs text-grafite/80">Escolha % ou R$. O desconto vale para cada cobrança: em R$, o mesmo valor sai de todas.</p>
            <p v-if="erroDoDesconto" :id="`${idDesconto}-erro`" class="mt-1 text-xs text-tinta-vermelha">{{ erroDoDesconto }}</p>
            <Transition name="surgir">
                <p v-if="aviso" aria-hidden="true" class="mt-1 text-xs text-tinta-laranja">{{ aviso }}</p>
            </Transition>
            <!-- Aviso que entra por v-if não é anunciado; a região fica sempre presente e só o texto muda. -->
            <p class="sr-only" aria-live="polite">{{ aviso }}</p>
        </div>
        <div v-if="elegiveis.length" class="relative mt-4 max-h-64 overflow-auto border border-linha">
            <table class="w-full text-xs tabular-nums">
                <caption class="sr-only">Prévia da baixa: valor, desconto e valor pago de cada cobrança, em reais</caption>
                <thead class="sticky top-0 bg-white font-estreita text-grafite/80">
                    <tr class="border-b border-linha text-right">
                        <th scope="col" class="px-2 py-1 text-left font-normal">Cobrança</th>
                        <th scope="col" class="px-2 py-1 font-normal">Valor</th>
                        <th scope="col" class="px-2 py-1 font-normal">Desconto</th>
                        <th scope="col" class="px-2 py-1 font-normal">Pago</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(cobranca, i) in elegiveis" :key="cobranca.id" class="border-b border-linha/60 text-right">
                        <td class="w-full max-w-0 px-2 py-1 text-left">
                            <p class="truncate">{{ cobranca.cliente }}</p>
                            <p class="text-grafite/70">Vence {{ cobranca.vencimento }}</p>
                        </td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].valor) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].desconto) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.linhas[i].pago) }}</td>
                    </tr>
                </tbody>
                <tfoot class="font-semibold">
                    <tr class="text-right">
                        <th scope="row" class="px-2 py-1 text-left">Total</th>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.totais.valor) }}</td>
                        <td class="px-2 py-1">{{ formatarCentavos(previa.totais.desconto) }}</td>
                        <td class="px-2 py-1"><span :key="previa.totais.pago" class="destacar inline-block">{{ formatarCentavos(previa.totais.pago) }}</span></td>
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
