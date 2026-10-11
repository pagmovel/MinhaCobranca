<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Campo from './Campo.vue';

const props = defineProps({
    feriados: { type: Array, required: true },
    nacionais: { type: Array, required: true },
    vencimentos: { type: Array, required: true },
});

const tipos = [
    { valor: 'anual', rotulo: 'Todo ano no mesmo dia' },
    { valor: 'corpus_christi', rotulo: 'Corpus Christi (data móvel)' },
    { valor: 'unico', rotulo: 'Uma data só' },
];
const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

const editando = ref(null); // id do feriado em edição, 'novo' para inclusão
const confirmando = ref(null);
const ajustando = ref(false);
const form = useForm({ descricao: '', tipo: 'anual', dia: '', mes: '', data: '' });

/** O feriado novo entra como uma linha a mais no fim da lista: o formulário aparece onde a pessoa clicou. */
const linhas = computed(() => (editando.value === 'novo' ? [...props.feriados, { id: 'novo' }] : props.feriados));
// O servidor aceita até 1000 por pedido: acima disso, ajusta em partes (a prévia volta com as que faltam).
const POR_PEDIDO = 1000;
const erroAjuste = ref('');
const resumoAjuste = computed(() => (props.vencimentos.length === 1
    ? '1 cobrança em aberto vence em dia não útil'
    : `${props.vencimentos.length} cobranças em aberto vencem em dia não útil`));
const rotuloAjuste = computed(() => {
    const total = props.vencimentos.length;
    if (total > POR_PEDIDO) {
        return `Ajustar ${POR_PEDIDO} de ${total} cobranças`;
    }

    return total === 1 ? 'Ajustar 1 cobrança' : `Ajustar ${total} cobranças`;
});

async function abrir(feriado = null) {
    form.clearErrors();
    form.descricao = feriado?.descricao ?? '';
    form.tipo = feriado?.tipo ?? 'anual';
    form.dia = feriado?.dia ?? '';
    form.mes = feriado?.mes ?? '';
    form.data = feriado?.data ?? '';
    confirmando.value = null;
    editando.value = feriado ? feriado.id : 'novo';
    await nextTick();
    const campo = document.getElementById('feriado_descricao');
    campo?.scrollIntoView({ block: 'nearest' });
    campo?.focus();
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'novo') {
        form.post('/perfil/feriados', opcoes);
    } else {
        form.put(`/perfil/feriados/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/feriados/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});

const ERRO_GENERICO = 'Não foi possível ajustar agora. Tente de novo.';

const ajustar = () => router.post('/perfil/feriados/ajustar', { ids: props.vencimentos.slice(0, POR_PEDIDO).map((item) => item.id) }, {
    preserveScroll: true,
    onStart: () => {
        ajustando.value = true;
        erroAjuste.value = '';
    },
    // Erro sempre na tela, com o botão de tentar de novo ao lado (padrão 2).
    onError: (erros) => {
        erroAjuste.value = Object.values(erros)[0] ?? ERRO_GENERICO;
    },
    // Sessão expirada (419), erro no servidor (500) ou qualquer outra resposta HTTP fora de validação: mesmo lugar,
    // mesma mensagem; `false` evita o diálogo de erro padrão do Inertia (tela cinza fora do padrão visual do projeto).
    onHttpException: () => {
        erroAjuste.value = ERRO_GENERICO;
        return false;
    },
    // Sem conexão com o servidor.
    onNetworkError: () => {
        erroAjuste.value = ERRO_GENERICO;
    },
    onFinish: () => { ajustando.value = false; },
});
</script>

<template>
    <section id="feriados" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-feriados">
        <h2 id="titulo-feriados" class="text-sm font-semibold">Feriados</h2>
        <p class="text-sm text-grafite/80">
            Vencimento de contrato que cair em sábado, domingo ou feriado vai para o primeiro dia útil seguinte. Os feriados
            nacionais, o Carnaval e a Sexta-feira da Paixão já contam; cadastre aqui os do seu estado e da sua cidade.
        </p>

        <!-- Região viva sempre presente, só com a frase-resumo (lista e botão ficam fora do anúncio). -->
        <p class="sr-only" aria-live="polite">{{ vencimentos.length ? `${resumoAjuste}.` : '' }}</p>
        <Transition name="surgir">
            <div v-if="vencimentos.length" class="flex flex-col gap-3 border border-carbono/40 bg-white px-4 py-3">
                <p class="text-sm font-semibold">{{ resumoAjuste }}</p>
                <p class="text-sm text-grafite/80">
                    Ainda não foram enviadas. Ajustar muda o vencimento para o próximo dia útil e refaz o recibo com o mesmo número.
                </p>
                <TransitionGroup tag="ul" name="lista" class="relative divide-y divide-linha border border-linha">
                    <li v-for="item in vencimentos" :key="item.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-3 py-2 text-sm">
                        <span class="min-w-0 font-semibold break-words">{{ item.cliente }}</span>
                        <span class="tabular-nums">
                            {{ item.vencimento }} ({{ item.motivo }})
                            <span aria-hidden="true">→</span><span class="sr-only">passa para</span>
                            {{ item.novo_vencimento }}
                        </span>
                        <span v-if="item.tem_boleto" class="w-full font-estreita text-xs font-semibold">Tem boleto: confira a data no banco.</span>
                    </li>
                </TransitionGroup>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="botao" :disabled="ajustando" @click="ajustar">{{ rotuloAjuste }}</button>
                    <p v-if="erroAjuste" role="alert" class="text-sm text-tinta-vermelha">{{ erroAjuste }}</p>
                </div>
            </div>
        </Transition>

        <TransitionGroup v-if="linhas.length" tag="ul" name="lista" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="feriado in linhas" :key="feriado.id" :class="editando === feriado.id ? 'bg-papel' : ''">
                <form
                    v-if="editando === feriado.id"
                    class="grid gap-4 px-4 py-4 sm:grid-cols-6"
                    :aria-label="feriado.id === 'novo' ? 'Novo feriado' : `Editar feriado ${feriado.descricao}`"
                    @submit.prevent="salvar"
                    @keydown.esc="editando = null"
                >
                    <p class="text-sm font-semibold sm:col-span-6">{{ feriado.id === 'novo' ? 'Novo feriado' : 'Editando este feriado' }}</p>
                    <Campo id="feriado_descricao" rotulo="Nome do feriado" :erro="form.errors.descricao" class="sm:col-span-3">
                        <input id="feriado_descricao" v-model="form.descricao" maxlength="60" class="entrada" required>
                    </Campo>
                    <Campo id="feriado_tipo" rotulo="Quando" :erro="form.errors.tipo" class="sm:col-span-3">
                        <select id="feriado_tipo" v-model="form.tipo" class="entrada">
                            <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                        </select>
                    </Campo>
                    <template v-if="form.tipo === 'anual'">
                        <Campo id="feriado_dia" rotulo="Dia" :erro="form.errors.dia" class="sm:col-span-2">
                            <input id="feriado_dia" v-model="form.dia" type="number" min="1" max="31" class="entrada" required>
                        </Campo>
                        <Campo id="feriado_mes" rotulo="Mês" :erro="form.errors.mes" class="sm:col-span-4">
                            <select id="feriado_mes" v-model="form.mes" class="entrada" required>
                                <option value="" disabled>Escolha o mês</option>
                                <option v-for="(nome, indice) in meses" :key="nome" :value="indice + 1">{{ nome }}</option>
                            </select>
                        </Campo>
                    </template>
                    <Campo v-else-if="form.tipo === 'unico'" id="feriado_data" rotulo="Data" :erro="form.errors.data" class="sm:col-span-3">
                        <input id="feriado_data" v-model="form.data" type="date" class="entrada" required>
                    </Campo>
                    <p v-else class="font-estreita text-xs text-grafite/70 sm:col-span-6">
                        A data muda todo ano (60 dias depois da Páscoa) e é calculada sozinha.
                    </p>
                    <div class="flex flex-wrap gap-2 sm:col-span-6">
                        <button class="botao" :disabled="form.processing">{{ feriado.id === 'novo' ? 'Cadastrar feriado' : 'Salvar feriado' }}</button>
                        <button type="button" class="botao-secundario" @click="editando = null">Cancelar</button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ feriado.descricao }}</p>
                        <p class="font-estreita text-sm text-grafite/80">{{ feriado.quando }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="botao-secundario" @click="abrir(feriado)">Editar</button>
                        <button v-if="confirmando !== feriado.id" type="button" class="botao-perigo" @click="confirmando = feriado.id">Excluir</button>
                        <template v-else>
                            <button type="button" class="botao-perigo-confirmar" @click="excluir(feriado.id)">Confirmar exclusão</button>
                            <button type="button" class="botao-secundario" @click="confirmando = null">Manter feriado</button>
                        </template>
                    </div>
                </div>
            </li>
        </TransitionGroup>
        <p v-else class="text-sm text-grafite/80">Nenhum feriado local cadastrado.</p>

        <div v-if="editando !== 'novo'">
            <button type="button" class="botao-secundario" @click="abrir()">Novo feriado</button>
        </div>

        <details class="text-sm">
            <summary class="cursor-pointer font-estreita text-grafite/80">Feriados nacionais deste ano (já contam, não precisa cadastrar)</summary>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                <li v-for="item in nacionais" :key="item.data" class="tabular-nums">{{ item.data }} · {{ item.descricao }}</li>
            </ul>
        </details>
    </section>
</template>
