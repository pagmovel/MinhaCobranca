<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    contratoId: { type: Number, required: true },
    fim: { type: String, required: true },
    editavel: { type: Boolean, default: false },
    minimo: { type: String, default: '' },
    rotuloConfirmar: { type: String, required: true },
    processando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'voltar']);

const dataFim = ref(props.fim);
const emCurso = ref('proporcional');
const previa = ref(null);
const carregando = ref(false);
const erro = ref('');
let timer = null;
let pedido = null;

watch(() => props.fim, (valor) => { dataFim.value = valor; });

const dataBr = (iso) => iso.split('-').reverse().join('/');

async function carregar() {
    pedido?.abort();
    erro.value = '';

    if (!dataFim.value) {
        previa.value = null;
        carregando.value = false;
        return;
    }

    const meu = new AbortController();
    pedido = meu;
    carregando.value = true;

    try {
        const parametros = new URLSearchParams({ fim: dataFim.value, em_curso: emCurso.value });
        const resposta = await fetch(`/contratos/${props.contratoId}/encerramento?${parametros}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: meu.signal,
        });
        const corpo = await resposta.json().catch(() => null);

        if (resposta.ok && corpo) {
            previa.value = corpo;
        } else {
            previa.value = null;
            erro.value = corpo?.errors?.fim?.[0] ?? 'Não foi possível montar a prévia agora. Tente de novo.';
        }
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            previa.value = null;
            erro.value = 'Não foi possível montar a prévia agora. Tente de novo.';
        }
    } finally {
        if (pedido === meu) {
            carregando.value = false;
        }
    }
}

watch([dataFim, emCurso], () => {
    clearTimeout(timer);
    // Já trava o "confirmar": a prévia na tela é da escolha anterior até a nova chegar.
    carregando.value = true;
    timer = setTimeout(carregar, 300);
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(timer);
    pedido?.abort();
});

const textos = {
    manter: 'Continua em aberto',
    cancelar: 'Será cancelada',
    emitir: 'Emitida agora',
    nao_emitir: 'Não será cobrada',
};

function oQueAcontece(acao) {
    if (acao.agendada) {
        return acao.acao === 'proporcional'
            ? `Sai perto do vencimento, proporcional (${acao.dias})`
            : (acao.acao === 'emitir' ? 'Sai perto do vencimento, como sempre' : textos.nao_emitir);
    }
    if (acao.acao !== 'proporcional') {
        return textos[acao.acao];
    }
    if (acao.nova) {
        return `Emitida agora, proporcional (${acao.dias})`;
    }

    const texto = acao.enviada
        ? `Vira proporcional (${acao.dias}): esta, já enviada, é cancelada e sai uma cobrança nova`
        : `Vira proporcional (${acao.dias}), com o mesmo número de recibo`;

    return acao.boleto_novo ? `${texto}. O boleto informado era do valor cheio: informe um boleto novo na cobrança.` : texto;
}

const agendado = computed(() => Boolean(previa.value?.acoes.some((acao) => acao.agendada)));

const confirmar = () => emit('confirmar', { fim: dataFim.value, em_curso: emCurso.value });
</script>

<template>
    <div class="aparecer grid gap-3 border border-tinta-vermelha/40 bg-white p-4">
        <label v-if="editavel" class="grid max-w-xs gap-1 text-sm">
            <span class="font-estreita text-xs font-medium text-grafite/75">Data de fim</span>
            <input v-model="dataFim" type="date" :min="minimo" class="entrada" required>
        </label>

        <div class="relative" aria-live="polite">
            <p class="flex items-center gap-2 text-sm font-medium">
                O que acontece com as cobranças
                <span v-if="carregando" class="h-4 w-4 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
                <span v-if="carregando" class="sr-only" role="status">Montando a prévia…</span>
            </p>
            <Transition name="surgir">
                <p v-if="erro" class="mt-2 text-sm text-tinta-vermelha">{{ erro }}</p>
            </Transition>
            <template v-if="previa && !erro">
                <p v-if="!previa.acoes.length" class="mt-2 text-sm text-grafite/80">
                    Nenhuma cobrança pendente: só o contrato é encerrado.
                </p>
                <div v-else class="relative mt-2 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="font-estreita text-xs text-grafite/75">
                            <tr>
                                <th class="py-1 pr-3 font-medium">Período</th>
                                <th class="py-1 pr-3 font-medium">Vencimento</th>
                                <th class="py-1 pr-3 text-right font-medium">Valor</th>
                                <th class="py-1 font-medium">O que acontece</th>
                            </tr>
                        </thead>
                        <TransitionGroup name="lista" tag="tbody">
                            <tr v-for="acao in previa.acoes" :key="acao.periodo + acao.acao" class="border-t border-carbono/10">
                                <td class="py-1 pr-3 whitespace-nowrap">{{ acao.periodo }}</td>
                                <td class="py-1 pr-3">{{ acao.vencimento }}</td>
                                <td class="py-1 pr-3 text-right whitespace-nowrap" :class="{ 'line-through text-grafite/60': ['cancelar', 'nao_emitir'].includes(acao.acao) }">
                                    R$ {{ acao.valor }}
                                </td>
                                <td class="py-1" :class="{ 'text-tinta-vermelha': acao.acao === 'cancelar' }">{{ oQueAcontece(acao) }}</td>
                            </tr>
                        </TransitionGroup>
                    </table>
                </div>
                <fieldset v-if="previa.tem_em_curso" class="mt-3 min-w-0">
                    <legend class="mb-1 font-estreita text-xs font-medium text-grafite/75">
                        Período em curso em {{ dataBr(dataFim) }}
                    </legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="emCurso" type="radio" value="proporcional"> Cobrar proporcional aos dias usados</label>
                        <label class="flex items-center gap-2"><input v-model="emCurso" type="radio" value="cancelar"> Não cobrar o período</label>
                    </div>
                </fieldset>
            </template>
            <p v-if="agendado" class="mt-2 text-sm">
                O contrato continua ativo até {{ dataBr(dataFim) }} e para de gerar cobranças depois disso.
            </p>
            <p class="mt-2 text-xs text-grafite/70">
                Cobranças vencidas de períodos até o fim continuam em aberto. Pagas e canceladas não mudam (devolução de período já pago não é feita aqui).
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" class="botao-perigo-confirmar" :disabled="processando || carregando || !previa || Boolean(erro)" @click="confirmar">
                {{ rotuloConfirmar }}
            </button>
            <button type="button" class="botao-secundario" @click="emit('voltar')">Voltar</button>
        </div>
    </div>
</template>
