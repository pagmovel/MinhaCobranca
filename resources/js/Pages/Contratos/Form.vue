<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CampoCliente from '../../Components/CampoCliente.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';
import DescricaoComplementar from '../../Components/DescricaoComplementar.vue';
import EncerramentoContrato from '../../Components/EncerramentoContrato.vue';
import PerguntaRetroativos from '../../Components/PerguntaRetroativos.vue';
import ReativarContrato from '../../Components/ReativarContrato.vue';
import RenovarContrato from '../../Components/RenovarContrato.vue';
import SecaoItens from '../../Components/SecaoItens.vue';
import { mensagemBloqueioDeCriacao } from '../../progresso.js';

const props = defineProps({
    contrato: { type: Object, default: null },
    clienteAtual: { type: Object, default: null },
    periodicidades: { type: Array, required: true },
    modalidades: { type: Array, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    cadastroRapido: { type: Object, required: true },
});

// Cliente escolhido (formato da autosugestão): a descrição oferece incluir o apelido.
const cliente = ref(props.clienteAtual);

// Vencido: os campos já sugerem o próximo período (hoje + mesma duração da vigência anterior), não as datas
// (vencidas) do contrato antigo — editável depois. "Mudou período" compara contra essa sugestão, não o valor
// bruto antigo, senão a seção "Recomeçar" apareceria sozinha ao abrir a tela, sem o usuário pedir.
const baseInicio = props.contrato?.vencido ? props.contrato.sugestao_renovacao.data_inicio : props.contrato?.data_inicio;
const baseFim = props.contrato?.vencido ? props.contrato.sugestao_renovacao.data_fim : props.contrato?.data_fim;

// `reativar` só existe para contrato encerrado: sem ele marcado, salvar não desfaz o encerramento.
const form = useForm(props.contrato ? { ...props.contrato, data_inicio: baseInicio, data_fim: baseFim, reativar: false, gerar_retroativas: null } : {
    gerar_retroativas: null,
    cliente_id: '',
    descricao: '',
    itens: [],
    periodicidade: 'mensal',
    modalidade: 'prepago',
    dia_vencimento: 10,
    data_inicio: new Date().toLocaleDateString('sv-SE'),
    data_fim: '',
    ativo: true,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
});

const semItens = Boolean(props.contrato && !props.contrato.itens.length);

// computed(), não uma const direta: page.props.progresso é substituído a cada visita do Inertia, e só um
// computed continua reativo a essa troca.
const progresso = computed(() => usePage().props.progresso);
// "Recomeçar"/"Renovar" criam um contrato novo por baixo (POST, gated no servidor por progresso:liberado);
// sem isso, o clique só seria bloqueado depois, descartando o que a pessoa editou no formulário.
const liberadoParaCriar = computed(() => Boolean(progresso.value?.liberado));
const avisoBloqueioDeCriacao = computed(() => progresso.value && mensagemBloqueioDeCriacao(progresso.value));


const dataBr = (iso) => (iso ? iso.split('-').reverse().join('/') : '');
const hoje = new Date().toLocaleDateString('sv-SE');

/** Início ou periodicidade mudou (ou contrato novo): a tela pergunta sobre os períodos que já terminaram. */
const perguntaRetroativos = computed(() => !props.contrato
    || form.data_inicio !== baseInicio
    || form.periodicidade !== props.contrato.periodicidade);

/**
 * Contrato com cobranças não muda a âncora dos períodos sem um caminho explícito: sem cobrança paga, corrigir
 * (as pendentes são canceladas); com paga, recomeçar (encerra e cria outro a partir de uma data posterior).
 */
const mudaPeriodos = computed(() => Boolean(props.contrato?.tem_cobrancas) && (
    form.data_inicio !== baseInicio
    || form.periodicidade !== props.contrato.periodicidade
    || form.modalidade !== props.contrato.modalidade
));
const modoEscolhido = ref(null);
const modo = computed(() => (props.contrato?.tem_pagas ? 'recomecar' : (modoEscolhido.value ?? 'corrigir')));

const salvar = () => {
    if (!props.contrato) {
        form.post('/contratos');
        return;
    }

    const corrigir = mudaPeriodos.value && modo.value === 'corrigir';
    form.transform((dados) => ({ ...dados, corrigir }))
        .put(`/contratos/${props.contrato.id}`, { onFinish: () => form.transform((dados) => dados) });
};
const inicioSemRecomeco = computed(() => Boolean(form.data_inicio) && form.data_inicio <= props.contrato.data_inicio);
const vespera = computed(() => {
    const data = new Date(`${form.data_inicio}T12:00:00`);
    data.setDate(data.getDate() - 1);

    return data.toLocaleDateString('sv-SE');
});

const desfazerMudancaDePeriodos = () => {
    modoEscolhido.value = null;
    form.data_inicio = baseInicio;
    form.periodicidade = props.contrato.periodicidade;
    form.modalidade = props.contrato.modalidade;
};

const recomecar = ({ em_curso }) => form
    .transform((dados) => ({ ...dados, em_curso }))
    .post(`/contratos/${props.contrato.id}/recomecar`, { onFinish: () => form.transform((dados) => dados) });

const encerrando = ref(false);
const enviandoEncerramento = ref(false);
const encerrar = (dados) => router.post(`/contratos/${props.contrato.id}/encerrar`, dados, {
    preserveScroll: true,
    onStart: () => { enviandoEncerramento.value = true; },
    onFinish: () => { enviandoEncerramento.value = false; },
    onSuccess: () => { encerrando.value = false; },
});

const renovando = ref(false);
const enviandoRenovacao = ref(false);
const erroRenovacao = ref('');
const renovar = (dados) => router.post(`/contratos/${props.contrato.id}/renovar`, dados, {
    preserveScroll: true,
    onStart: () => { enviandoRenovacao.value = true; erroRenovacao.value = ''; },
    onFinish: () => { enviandoRenovacao.value = false; },
    onSuccess: () => { renovando.value = false; },
    onError: (erros) => { erroRenovacao.value = erros.data_fim ?? 'Não foi possível renovar agora. Tente de novo.'; },
});

// Contrato vencido (fim já passado, nunca passou por "Encerrar"): renovar cria um contrato novo, com os mesmos
// itens/preço do antigo para ajustar — mesmo padrão do formulário principal, sem pedir para redigitar tudo.
const diaSeguinte = (iso) => {
    const data = new Date(`${iso}T12:00:00`);
    data.setDate(data.getDate() + 1);

    return data.toLocaleDateString('sv-SE');
};
const renovandoVencido = ref(false);
// JSON (não structuredClone): props.contrato é reativo (Proxy), e structuredClone lança DataCloneError nele.
const formRenovacaoVencido = useForm(props.contrato ? {
    data_inicio: diaSeguinte(props.contrato.data_fim),
    itens: JSON.parse(JSON.stringify(props.contrato.itens)),
} : { data_inicio: '', itens: [] });
const confirmarRenovacaoVencido = () => formRenovacaoVencido.post(`/contratos/${props.contrato.id}/renovar-vencido`, { preserveScroll: true });
</script>

<template>
    <CamadaApp>
        <Link href="/contratos" class="font-estreita text-sm text-carbono underline">Voltar para contratos</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">
            {{ contrato?.vencido ? 'Renovar contrato' : (contrato ? 'Editar contrato' : 'Novo contrato') }}
        </h1>


        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <CampoCliente :form="form" :inicial="clienteAtual" :opcoes="cadastroRapido" @escolhido="cliente = $event" />
            </Campo>
            <p v-if="semItens" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                Este contrato ainda não tem itens (valor atual: R$ {{ contrato.valor }}). Inclua pelo menos um; ao salvar, o
                valor passa a ser a soma dos itens.
            </p>
            <SecaoItens :form="form" />
            <DescricaoComplementar :form="form" :cliente="cliente" />
            <Campo id="periodicidade" rotulo="Periodicidade" :erro="form.errors.periodicidade" class="sm:col-span-3">
                <select id="periodicidade" v-model="form.periodicidade" class="entrada">
                    <option v-for="p in periodicidades" :key="p.valor" :value="p.valor">{{ p.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="dia_vencimento" rotulo="Dia do vencimento" :erro="form.errors.dia_vencimento" class="sm:col-span-3">
                <input id="dia_vencimento" v-model="form.dia_vencimento" type="number" min="1" max="31" class="entrada" required>
            </Campo>
            <fieldset class="sm:col-span-6">
                <legend class="mb-2 font-estreita text-xs font-medium text-grafite/75">Modalidade</legend>
                <div class="flex flex-wrap gap-4">
                    <label v-for="modalidade in modalidades" :key="modalidade.valor" class="flex items-center gap-2 text-sm">
                        <input v-model="form.modalidade" type="radio" name="modalidade" :value="modalidade.valor"> {{ modalidade.rotulo }}
                    </label>
                </div>
                <p class="mt-1 text-xs text-grafite/70">
                    {{ form.modalidade === 'pospago'
                        ? 'Vence no dia escolhido depois que o período termina (serviço de outubro vence em novembro).'
                        : 'Vence no dia escolhido do mês em que o período começa.' }}
                </p>
                <p v-if="form.errors.modalidade" class="text-xs text-tinta-vermelha">{{ form.errors.modalidade }}</p>
            </fieldset>
            <Campo id="data_inicio" rotulo="Início do primeiro período" :erro="form.errors.data_inicio" class="sm:col-span-3">
                <input id="data_inicio" v-model="form.data_inicio" type="date" class="entrada" required>
                <p v-if="contrato?.tem_cobrancas" class="mt-1 text-xs" :class="mudaPeriodos ? 'text-tinta-vermelha' : 'text-grafite/70'">
                    {{ contrato.tem_pagas
                        ? 'Este contrato tem cobrança paga: mudar o início, a periodicidade ou a modalidade encerra este contrato e cria um novo'
                        : 'Este contrato já tem cobranças: mudar o início, a periodicidade ou a modalidade pede confirmação' }}{{ mudaPeriodos ? ' (veja abaixo).' : '.' }}
                </p>
            </Campo>
            <Campo id="data_fim" rotulo="Fim do contrato (opcional)" :erro="form.errors.data_fim" class="sm:col-span-3">
                <input id="data_fim" v-model="form.data_fim" type="date" class="entrada" :disabled="contrato?.encerramento_agendado">
                <p v-if="contrato?.encerramento_agendado" class="mt-1 text-xs text-grafite/70">
                    Encerramento já agendado: para mudar a data, use "Encerrar contrato" abaixo.
                </p>
            </Campo>
            <PerguntaRetroativos
                v-if="perguntaRetroativos && !(mudaPeriodos && modo === 'recomecar')"
                v-model="form.gerar_retroativas"
                :data-inicio="form.data_inicio"
                :periodicidade="form.periodicidade"
                :erro="form.errors.gerar_retroativas"
            />
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            <div v-if="contrato && !contrato.ativo" class="grid gap-2 sm:col-span-6">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.reativar" type="checkbox"> Reativar contrato
                </label>
                <ReativarContrato v-if="form.reativar" :contrato-id="contrato.id" />
            </div>
            <Transition name="surgir" mode="out-in">
                <section v-if="mudaPeriodos && modo === 'corrigir'" key="corrigir" class="grid gap-3 sm:col-span-6">
                    <h2 class="text-lg font-semibold">Corrigir o contrato</h2>
                    <div v-if="contrato.cobrancas_pendentes.length" class="grid gap-2 border border-tinta-vermelha/40 bg-white p-4 text-sm">
                        <p>
                            {{ contrato.cobrancas_pendentes.length === 1 ? 'Esta cobrança pendente será cancelada' : 'Estas cobranças pendentes serão canceladas' }}
                            (o número do recibo não volta) e o contrato passa a gerar pelos dados novos:
                        </p>
                        <div class="relative overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="font-estreita text-xs text-grafite/75">
                                    <tr>
                                        <th class="py-1 pr-3 font-medium">Período</th>
                                        <th class="py-1 pr-3 font-medium">Vencimento</th>
                                        <th class="py-1 pr-3 text-right font-medium">Valor</th>
                                        <th class="py-1 font-medium">Situação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cobranca in contrato.cobrancas_pendentes" :key="cobranca.periodo" class="border-t border-carbono/10">
                                        <td class="py-1 pr-3 whitespace-nowrap">{{ cobranca.periodo }}</td>
                                        <td class="py-1 pr-3">{{ cobranca.vencimento }}</td>
                                        <td class="py-1 pr-3 text-right whitespace-nowrap text-grafite/60 line-through">R$ {{ cobranca.valor }}</td>
                                        <td class="py-1">{{ cobranca.enviada ? 'Já enviada ao cliente' : 'Não enviada' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p v-else class="text-sm">
                        Nenhuma cobrança pendente: as canceladas deixam de ficar ligadas a este contrato, e ele passa a gerar
                        pelos dados novos.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button :class="contrato.cobrancas_pendentes.length ? 'botao-perigo-confirmar' : 'botao'" :disabled="form.processing">
                            {{ contrato.cobrancas_pendentes.length ? 'Cancelar as pendentes e salvar a correção' : 'Salvar a correção' }}
                        </button>
                        <button type="button" class="botao-secundario" @click="desfazerMudancaDePeriodos">Desfazer a mudança</button>
                    </div>
                    <p v-if="liberadoParaCriar && form.data_inicio > contrato.data_inicio && contrato.cobrancas_pendentes.length" class="text-sm">
                        Essas cobranças são devidas e devem continuar?
                        <button type="button" class="underline" @click="modoEscolhido = 'recomecar'">Recomeçar o contrato em vez de corrigir</button>
                    </p>
                </section>
                <section v-else-if="mudaPeriodos" key="recomecar" class="grid gap-3 sm:col-span-6">
                    <h2 class="text-lg font-semibold">Recomeçar o contrato</h2>
                    <p v-if="!liberadoParaCriar" class="border border-carbono bg-white px-4 py-3 text-sm">
                        {{ avisoBloqueioDeCriacao }}
                        <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
                    </p>
                    <template v-else-if="inicioSemRecomeco">
                        <p class="text-sm">
                            O contrato novo precisa começar depois de {{ dataBr(contrato.data_inicio) }} — antes disso, os
                            períodos se sobreporiam aos já cobrados. Escolha um início posterior ou desfaça a mudança.
                        </p>
                    </template>
                    <template v-else-if="form.data_inicio">
                        <p class="text-sm">
                            Este contrato termina em {{ dataBr(vespera) }} e um novo começa em {{ dataBr(form.data_inicio) }}
                            com os dados deste formulário (cliente, itens, preços, descrição e pagamento).
                        </p>
                        <EncerramentoContrato
                            :contrato-id="contrato.id"
                            :fim="vespera"
                            :rotulo-confirmar="`Encerrar este contrato e criar um novo a partir de ${dataBr(form.data_inicio)}`"
                            :processando="form.processing"
                            @confirmar="recomecar"
                            @voltar="desfazerMudancaDePeriodos"
                        />
                    </template>
                    <div v-if="inicioSemRecomeco">
                        <button type="button" class="botao-secundario" @click="desfazerMudancaDePeriodos">Desfazer a mudança</button>
                    </div>
                    <p v-if="!contrato.tem_pagas" class="text-sm">
                        <button type="button" class="underline" @click="modoEscolhido = 'corrigir'">Corrigir em vez de recomeçar</button>
                    </p>
                </section>
                <div v-else key="salvar" class="sm:col-span-6">
                    <button class="botao" :disabled="form.processing">Salvar contrato</button>
                </div>
            </Transition>
        </form>

        <section v-if="contrato?.vencido" class="mt-10 grid max-w-3xl gap-4 border-t border-carbono/15 pt-6 sm:grid-cols-6">
            <h2 class="text-lg font-semibold sm:col-span-6">Renovar contrato</h2>
            <p class="text-sm sm:col-span-6">
                Este contrato venceu em {{ dataBr(contrato.data_fim) }} e não gera mais cobranças. Renovar encerra este
                contrato e cria um novo, com os mesmos dados — ajuste os itens e os preços se precisar.
            </p>
            <p v-if="!liberadoParaCriar" class="sm:col-span-6 border border-carbono bg-white px-4 py-3 text-sm">
                {{ avisoBloqueioDeCriacao }}
                <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
            </p>
            <div v-else-if="!renovandoVencido" class="sm:col-span-6">
                <button type="button" class="botao-secundario" @click="renovandoVencido = true">Renovar contrato…</button>
            </div>
            <template v-else>
                <Campo id="renovacao_data_inicio" rotulo="A partir de" :erro="formRenovacaoVencido.errors.data_inicio" class="sm:col-span-3">
                    <input id="renovacao_data_inicio" v-model="formRenovacaoVencido.data_inicio" type="date" class="entrada">
                </Campo>
                <SecaoItens :form="formRenovacaoVencido" escopo="renovacao_" />
                <div class="flex items-center gap-2 sm:col-span-6">
                    <button type="button" class="botao" :disabled="formRenovacaoVencido.processing" @click="confirmarRenovacaoVencido">
                        Confirmar renovação
                    </button>
                    <button type="button" class="botao-secundario" @click="renovandoVencido = false">Cancelar</button>
                </div>
            </template>
        </section>
        <section v-else-if="contrato?.ativo && !mudaPeriodos && contrato.encerramento_agendado" class="mt-10 grid max-w-3xl gap-3 border-t border-carbono/15 pt-6">
            <h2 class="text-lg font-semibold">Renovar contrato</h2>
            <p class="text-sm">
                Encerramento agendado para {{ dataBr(contrato.data_fim) }}: o contrato gera cobranças até lá.
            </p>
            <p v-if="form.isDirty" class="text-sm">
                Você mudou campos acima: salve o contrato antes de renovar, para não perder as mudanças.
            </p>
            <p v-else-if="!liberadoParaCriar" class="text-sm">
                {{ avisoBloqueioDeCriacao }}
                <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
            </p>
            <div v-else-if="!renovando">
                <button type="button" class="botao-secundario" @click="renovando = true">Renovar contrato…</button>
            </div>
            <RenovarContrato
                v-else
                :contrato-id="contrato.id"
                :erro="erroRenovacao"
                :processando="enviandoRenovacao"
                @confirmar="renovar"
                @voltar="renovando = false"
            />
        </section>
        <section v-else-if="contrato?.ativo && !mudaPeriodos" class="mt-10 grid max-w-3xl gap-3 border-t border-carbono/15 pt-6">
            <h2 class="text-lg font-semibold">Encerrar contrato</h2>
            <p class="text-sm text-grafite/80">
                Para de gerar cobranças a partir da data de fim. Antes de confirmar, você vê o que acontece com cada cobrança.
            </p>
            <p v-if="form.isDirty" class="text-sm">
                Você mudou campos acima: salve o contrato antes de encerrar, para não perder as mudanças.
            </p>
            <div v-else-if="!encerrando">
                <button type="button" class="botao-perigo" @click="encerrando = true">Encerrar contrato…</button>
            </div>
            <EncerramentoContrato
                v-else
                :contrato-id="contrato.id"
                :fim="hoje < contrato.data_inicio ? contrato.data_inicio : hoje"
                :minimo="contrato.data_inicio"
                editavel
                rotulo-confirmar="Confirmar encerramento"
                :processando="enviandoEncerramento"
                @confirmar="encerrar"
                @voltar="encerrando = false"
            />
        </section>
        <p v-else-if="contrato && !contrato.ativo" class="mt-6 max-w-3xl text-sm text-grafite/80">
            Contrato encerrado{{ contrato.data_fim ? ` em ${dataBr(contrato.data_fim)}` : '' }}: não gera novas cobranças.
        </p>
    </CamadaApp>
</template>
