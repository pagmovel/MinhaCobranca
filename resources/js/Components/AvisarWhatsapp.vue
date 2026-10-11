<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import { formatadores } from '../mascaras.js';
import { MENSAGEM_SUGERIDA, mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from '../mensagemWhatsapp.js';

const props = defineProps({
    aberto: { type: Boolean, required: true },
    ids: { type: Array, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const carregando = ref(false);
const erro = ref('');
const erroCopia = ref('');
const dados = ref(null);
const modeloEscolhido = ref(null);
const texto = ref('');
const telefone = ref('');
const areaTexto = ref(null);
const salvandoModelo = ref(false);
const novoModeloForm = useForm({ nome: '', texto: '' });

const padrao = computed(() => props.modelos.find((modelo) => modelo.padrao) ?? null);
// Sem fallback para o padrão aqui: `modeloEscolhido === null` já cobre os dois casos (nenhum padrão existe, ou a
// pessoa escolheu "Texto sugerido" de propósito) — nos dois, a sugestão genérica é o texto certo.
const textoDoModeloEscolhido = computed(() => props.modelos.find((modelo) => modelo.id === modeloEscolhido.value)?.texto
    ?? MENSAGEM_SUGERIDA);

function atualizarTexto() {
    if (!dados.value) {
        return;
    }
    texto.value = montarMensagem(textoDoModeloEscolhido.value, {
        cliente: dados.value.cliente,
        itens: dados.value.itens.map((item) => ({ nome: item.nome, valorCentavos: item.valor_centavos })),
        vencimentos: dados.value.vencimentos,
        totalCentavos: dados.value.total_centavos,
        prestador: props.prestador,
        chavePix: dados.value.chave_pix,
        contato: dados.value.contato,
    });
}

watch(() => props.aberto, async (aberto) => {
    if (!aberto) {
        return;
    }
    carregando.value = true;
    erro.value = '';
    erroCopia.value = '';
    salvandoModelo.value = false;
    dados.value = null;
    try {
        const parametros = new URLSearchParams(props.ids.map((id) => ['ids[]', id]));
        const resposta = await fetch(`/cobrancas/aviso?${parametros}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!resposta.ok) {
            throw new Error('falhou');
        }
        dados.value = await resposta.json();
        modeloEscolhido.value = padrao.value?.id ?? null;
        telefone.value = numeroWhatsapp(dados.value.telefone) ? formatadores.telefone(dados.value.telefone) : '';
        atualizarTexto();
    } catch {
        erro.value = 'Não foi possível carregar os dados do aviso. Tente de novo.';
    } finally {
        carregando.value = false;
    }
});
watch(modeloEscolhido, atualizarTexto);

const numeroValido = computed(() => numeroWhatsapp(telefone.value));
const avisoDeTamanho = computed(() => mensagemMuitoLonga(texto.value));

/** Dispara em segundo plano, sem travar o fechamento: "avisado em" é só um registro, pode avisar de novo se falhar. */
function marcarAvisadas() {
    router.post('/cobrancas/avisadas', { ids: props.ids }, { preserveScroll: true });
}

function abrirWhatsapp() {
    // Fecha ANTES de abrir a aba nova: window.open tira o foco desta aba, e o Chrome pausa requestAnimationFrame em
    // abas em segundo plano — a transição de saída do Modal travaria no meio para sempre, deixando o fundo escurecido
    // preso na tela (achado no navegador real, T24.9). A rede de segurança fica no próprio Modal.vue; fechar primeiro
    // aqui é só a primeira linha de defesa, para a transição já começar com a aba ainda em primeiro plano.
    const link = montarLinkWaMe(numeroValido.value, texto.value);
    emit('fechar');
    window.open(link, '_blank');
    marcarAvisadas();
}

/** Fallback para `navigator.clipboard`, indisponível fora de contexto seguro (ex.: http:// em dev — achado da revisão, T24.9). */
function copiarComExecCommand() {
    const campo = areaTexto.value;
    if (!campo) {
        throw new Error('campo de texto não encontrado');
    }
    campo.focus();
    campo.select();
    if (!document.execCommand('copy')) {
        throw new Error('execCommand(copy) recusou');
    }
}

async function copiarMensagem() {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(texto.value);
        } else {
            copiarComExecCommand();
        }
    } catch {
        try {
            copiarComExecCommand();
        } catch {
            erroCopia.value = 'Não foi possível copiar automaticamente. Selecione o texto acima e copie (Ctrl+C), '
                + 'ou confirme abaixo depois de copiar à mão.';
            return;
        }
    }
    erroCopia.value = '';
    emit('fechar');
    marcarAvisadas();
}

/** Saída manual do Review Focus 1 (sem celular válido): se nem o fallback de cópia funcionar, confirma na mão. */
function confirmarCopiaManual() {
    erroCopia.value = '';
    emit('fechar');
    marcarAvisadas();
}

function abrirSalvarModelo() {
    novoModeloForm.clearErrors();
    novoModeloForm.nome = '';
    salvandoModelo.value = true;
}

function salvarModelo() {
    novoModeloForm.texto = texto.value;
    novoModeloForm.post('/perfil/mensagens', {
        preserveScroll: true,
        onSuccess: () => { salvandoModelo.value = false; },
    });
}
</script>

<template>
    <Modal :aberto="aberto" titulo="Avisar no WhatsApp" largura="max-w-xl" @fechar="emit('fechar')">
        <p v-if="carregando" class="text-sm text-grafite/80">Carregando…</p>
        <p v-else-if="erro" role="alert" class="text-sm text-tinta-vermelha">{{ erro }}</p>
        <div v-else-if="dados" class="grid gap-4">
            <div v-if="modelos.length">
                <label for="aviso_modelo" class="mb-1 block text-xs font-medium text-grafite/75">Modelo</label>
                <select id="aviso_modelo" v-model="modeloEscolhido" class="entrada">
                    <option :value="null">Texto sugerido</option>
                    <option v-for="modelo in modelos" :key="modelo.id" :value="modelo.id">{{ modelo.nome }}</option>
                </select>
            </div>
            <div>
                <label for="aviso_texto" class="mb-1 block text-xs font-medium text-grafite/75">Mensagem</label>
                <textarea id="aviso_texto" ref="areaTexto" v-model="texto" rows="6" class="entrada" />
                <p v-if="avisoDeTamanho" class="mt-1 text-xs text-grafite/70">
                    Mensagem longa: alguns aparelhos podem cortar o final.
                </p>
                <div v-if="modeloEscolhido === null" class="mt-1">
                    <button v-if="!salvandoModelo" type="button" class="text-xs text-grafite/80 underline" @click="abrirSalvarModelo">
                        Salvar como modelo
                    </button>
                    <form v-else class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="salvarModelo">
                        <div>
                            <label for="aviso_nome_modelo" class="mb-1 block text-xs font-medium text-grafite/75">Nome do modelo</label>
                            <input id="aviso_nome_modelo" v-model="novoModeloForm.nome" maxlength="60" class="entrada" required>
                            <p v-if="novoModeloForm.errors.nome" class="mt-1 text-xs text-tinta-vermelha">{{ novoModeloForm.errors.nome }}</p>
                        </div>
                        <button type="submit" class="botao-secundario text-xs" :disabled="novoModeloForm.processing">Salvar</button>
                        <button type="button" class="botao-secundario text-xs" @click="salvandoModelo = false">Cancelar</button>
                    </form>
                </div>
            </div>
            <div>
                <label for="aviso_telefone" class="mb-1 block text-xs font-medium text-grafite/75">Número do WhatsApp</label>
                <input id="aviso_telefone" v-model="telefone" @input="telefone = formatadores.telefone(telefone)" class="entrada" placeholder="(00) 00000-0000">
                <p v-if="!numeroValido" class="mt-1 text-xs text-grafite/70">Sem número de celular válido: digite um, ou copie a mensagem.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="botao" :disabled="!numeroValido" @click="abrirWhatsapp">Abrir WhatsApp</button>
                <button type="button" class="botao-secundario" @click="copiarMensagem">Copiar mensagem</button>
            </div>
            <div v-if="erroCopia" class="flex flex-wrap items-center gap-3">
                <p role="alert" class="text-sm text-tinta-vermelha">{{ erroCopia }}</p>
                <button type="button" class="botao-secundario text-xs" @click="confirmarCopiaManual">Já copiei, marcar como avisada</button>
            </div>
        </div>
    </Modal>
</template>
