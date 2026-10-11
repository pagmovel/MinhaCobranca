<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaCnpj from '../../Components/BuscaCnpj.vue';
import CamposEndereco from '../../Components/CamposEndereco.vue';

const props = defineProps({
    cliente: { type: Object, default: null },
    emAberto: { type: Object, default: null },
    modeloPadrao: { type: String, required: true },
    modelos: { type: Array, required: true },
    ufs: { type: Array, required: true },
});

const campos = ['nome', 'apelido', 'contato', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'];

const form = useForm({
    ...Object.fromEntries(campos.map((campo) => [campo, props.cliente?.[campo] ?? ''])),
    modelo: props.cliente?.modelo ?? props.modeloPadrao,
    regerar_enviadas: false,
});

// Trocar o modelo refaz os recibos das cobranças em aberto (Fase 22): a prévia diz quantas, antes de salvar.
const modeloMudou = computed(() => props.cliente && form.modelo !== props.cliente.modelo);
const naoEnviadas = props.emAberto?.nao_enviadas ?? 0;
const enviadas = props.emAberto?.enviadas ?? 0;
const cobrancas = (quantidade) => (quantidade === 1 ? '1 cobrança' : `${quantidade} cobranças`);
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
                <Campo id="apelido" rotulo="Apelido (opcional)" :erro="form.errors.apelido" class="sm:col-span-6">
                    <input id="apelido" v-model="form.apelido" maxlength="60" class="entrada" aria-describedby="apelido-ajuda">
                    <p id="apelido-ajuda" class="font-estreita text-xs text-grafite/70">
                        Nome curto que aparece nas telas e nas buscas (ex.: Regional Araci). Serve também para cadastrar outra
                        unidade com o mesmo CPF/CNPJ. Não sai no recibo; para pôr, use "Incluir o apelido na descrição" no
                        contrato ou na cobrança.
                    </p>
                </Campo>
                <Campo id="contato" rotulo="Pessoa de contato (opcional)" :erro="form.errors.contato" class="sm:col-span-6">
                    <input id="contato" v-model="form.contato" maxlength="100" class="entrada" aria-describedby="contato-ajuda">
                    <p id="contato-ajuda" class="font-estreita text-xs text-grafite/70">
                        Nome de quem você fala com no dia a dia (ex.: Wendel). Usado só nas mensagens de WhatsApp, pela
                        variável <code>{contato}</code>.
                    </p>
                </Campo>
                <BuscaCnpj :form="form" />
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
                <Transition name="surgir">
                    <div v-if="modeloMudou" role="status" class="flex flex-col gap-2 border border-carbono/40 bg-white px-4 py-3 text-sm">
                        <p v-if="naoEnviadas">
                            {{ cobrancas(naoEnviadas) }} em aberto e ainda não {{ naoEnviadas === 1 ? 'enviada passa' : 'enviadas passam' }}
                            para o modelo novo ao salvar (mesmo número de recibo).
                        </p>
                        <label v-if="enviadas" class="flex items-start gap-2">
                            <input v-model="form.regerar_enviadas" type="checkbox" class="mt-1">
                            <span>
                                Gerar de novo também {{ enviadas === 1 ? 'a cobrança já enviada' : `as ${enviadas} cobranças já enviadas` }}
                                e ainda não {{ enviadas === 1 ? 'paga' : 'pagas' }}. {{ enviadas === 1 ? 'Ela volta' : 'Elas voltam' }} para
                                "Para enviar" no painel, para você mandar o PDF novo.
                            </span>
                        </label>
                        <p class="font-estreita text-xs text-grafite/70">Recibos pagos e cancelados não mudam. As próximas cobranças já saem no modelo novo.</p>
                    </div>
                </Transition>
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
