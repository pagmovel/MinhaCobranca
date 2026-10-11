<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import { formatadores } from '../mascaras.js';
import { mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from '../mensagemWhatsapp.js';

const props = defineProps({
    aberto: { type: Boolean, required: true },
    ids: { type: Array, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const SUGESTAO = 'Olá {cliente}! Passando para avisar que {itens} está{s} vencendo em {vencimento}, no valor de {total}. '
    + 'Qualquer dúvida, estou à disposição.\n\n{prestador}';

const carregando = ref(false);
const erro = ref('');
const erroCopia = ref('');
const dados = ref(null);
const modeloEscolhido = ref(null);
const texto = ref('');
const telefone = ref('');

const padrao = computed(() => props.modelos.find((modelo) => modelo.padrao) ?? null);
// Sem fallback para o padrão aqui: `modeloEscolhido === null` já cobre os dois casos (nenhum padrão existe, ou a
// pessoa escolheu "Texto sugerido" de propósito) — nos dois, a sugestão genérica é o texto certo.
const textoDoModeloEscolhido = computed(() => props.modelos.find((modelo) => modelo.id === modeloEscolhido.value)?.texto
    ?? SUGESTAO.replace('{s}', dados.value?.itens.length > 1 ? 'm' : ''));

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
    });
}

watch(() => props.aberto, async (aberto) => {
    if (!aberto) {
        return;
    }
    carregando.value = true;
    erro.value = '';
    erroCopia.value = '';
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
    // Fecha ANTES de abrir a aba nova: window.open tira o foco desta aba, e o Chrome pausa a renderização de abas em
    // segundo plano — a transição de saída do Modal travaria no meio para sempre, deixando o fundo escurecido preso
    // na tela (achado no navegador real, T24.9). Fechando primeiro, a transição roda com a aba ainda em primeiro plano.
    const link = montarLinkWaMe(numeroValido.value, texto.value);
    emit('fechar');
    window.open(link, '_blank');
    marcarAvisadas();
}

async function copiarMensagem() {
    try {
        await navigator.clipboard.writeText(texto.value);
        erroCopia.value = '';
        emit('fechar');
        marcarAvisadas();
    } catch {
        // Ex.: página em http:// (sem contexto seguro) ou permissão de clipboard negada — a API fica indisponível.
        erroCopia.value = 'Não foi possível copiar automaticamente. Selecione o texto acima e copie (Ctrl+C).';
    }
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
                <textarea id="aviso_texto" v-model="texto" rows="6" class="entrada" />
                <p v-if="avisoDeTamanho" class="mt-1 text-xs text-grafite/70">
                    Mensagem longa: alguns aparelhos podem cortar o final.
                </p>
                <p v-if="!modelos.length" class="mt-1 text-xs text-grafite/70">
                    Sem modelo cadastrado.
                    <a href="/perfil#mensagens-whatsapp" class="underline">Salvar como modelo</a>
                </p>
            </div>
            <div>
                <label for="aviso_telefone" class="mb-1 block text-xs font-medium text-grafite/75">Número do WhatsApp</label>
                <input id="aviso_telefone" v-model="telefone" @input="telefone = formatadores.telefone(telefone)" class="entrada" placeholder="(00) 00000-0000">
                <p v-if="!numeroValido" class="mt-1 text-xs text-grafite/70">Sem número de celular válido: digite um, ou copie a mensagem.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="botao" :disabled="!numeroValido" @click="abrirWhatsapp">Abrir WhatsApp</button>
                <button type="button" class="botao-secundario" @click="copiarMensagem">Copiar mensagem</button>
                <p v-if="erroCopia" role="alert" class="text-sm text-tinta-vermelha">{{ erroCopia }}</p>
            </div>
        </div>
    </Modal>
</template>
