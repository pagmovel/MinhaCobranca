<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { montarMensagem } from '../mensagemWhatsapp.js';

const props = defineProps({
    modelos: { type: Array, required: true },
    prestador: { type: String, default: '' },
});

const VARIAVEIS = [
    { token: '{cliente}', legenda: 'nome do cliente' },
    { token: '{item}', legenda: 'nome do(s) item(ns), em frase' },
    { token: '{itens}', legenda: 'lista dos itens vencendo' },
    { token: '{vencimento}', legenda: 'data(s) de vencimento' },
    { token: '{total}', legenda: 'valor total' },
    { token: '{chave_pix}', legenda: 'sua chave PIX (quando a cobrança usa PIX)' },
    { token: '{prestador}', legenda: 'seu nome ou razão social' },
];

// Dados fictícios só para a prévia — a janela de envio real usa os dados da cobrança.
const EXEMPLO = {
    cliente: 'Fulano da Silva',
    itens: [{ nome: 'Hospedagem do domínio exemplo.com.br', valorCentavos: 8990 }],
    vencimentos: ['2026-10-15'],
    totalCentavos: 8990,
    chavePix: 'cobranca@suaempresa.com.br',
};

const editando = ref(null); // id do modelo em edição, 'novo' para inclusão
const confirmando = ref(null);
const form = useForm({ nome: '', texto: '', padrao: false });

const previa = computed(() => montarMensagem(form.texto, { ...EXEMPLO, prestador: props.prestador || 'Seu nome ou razão social' }));

const linhas = () => (editando.value === 'novo' ? [...props.modelos, { id: 'novo' }] : props.modelos);

async function abrir(modelo = null) {
    form.clearErrors();
    form.nome = modelo?.nome ?? '';
    form.texto = modelo?.texto ?? '';
    form.padrao = modelo?.padrao ?? false;
    confirmando.value = null;
    editando.value = modelo ? modelo.id : 'novo';
    await nextTick();
    document.getElementById('mensagem_nome')?.focus();
}

/**
 * Insere o token na posição do cursor — o foco some ao clicar no botão, então guarda a posição antes.
 * Busca por id (como `abrir()` faz com "mensagem_nome"), não por `ref`: o textarea está dentro de um `v-for`
 * (linha do modelo), e o Vue 3.5 guarda um `ref` em `v-for` como array — pegar `.value` direto dava
 * `campo.focus is not a function` e o token sempre ia para o fim do texto (achado da revisão, T24.9).
 */
function inserir(token) {
    const campo = document.getElementById('mensagem_texto');
    const inicio = campo?.selectionStart ?? form.texto.length;
    const fim = campo?.selectionEnd ?? form.texto.length;
    form.texto = form.texto.slice(0, inicio) + token + form.texto.slice(fim);
    nextTick(() => {
        campo?.focus();
        campo?.setSelectionRange(inicio + token.length, inicio + token.length);
    });
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'novo') {
        form.post('/perfil/mensagens', opcoes);
    } else {
        form.put(`/perfil/mensagens/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/mensagens/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});
</script>

<template>
    <section id="mensagens-whatsapp" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-mensagens-whatsapp">
        <h2 id="titulo-mensagens-whatsapp" class="text-sm font-semibold">Mensagens de WhatsApp</h2>
        <p class="text-sm text-grafite/80">
            Modelos usados para avisar clientes sobre cobranças vencendo. O marcado como padrão abre pronto na janela de envio.
        </p>

        <ul v-if="linhas().length" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="modelo in linhas()" :key="modelo.id" :class="editando === modelo.id ? 'bg-papel' : ''">
                <form
                    v-if="editando === modelo.id"
                    class="grid gap-4 px-4 py-4"
                    :aria-label="modelo.id === 'novo' ? 'Novo modelo' : `Editar modelo ${modelo.nome}`"
                    @submit.prevent="salvar"
                    @keydown.esc="editando = null"
                >
                    <p class="text-sm font-semibold">{{ modelo.id === 'novo' ? 'Novo modelo' : 'Editando este modelo' }}</p>
                    <div>
                        <label for="mensagem_nome" class="mb-1 block text-xs font-medium text-grafite/75">Nome do modelo</label>
                        <input id="mensagem_nome" v-model="form.nome" maxlength="60" class="entrada" required>
                        <p v-if="form.errors.nome" class="mt-1 text-xs text-tinta-vermelha">{{ form.errors.nome }}</p>
                    </div>
                    <div>
                        <label for="mensagem_texto" class="mb-1 block text-xs font-medium text-grafite/75">Texto</label>
                        <textarea id="mensagem_texto" v-model="form.texto" maxlength="2000" rows="5" class="entrada" required />
                        <p v-if="form.errors.texto" class="mt-1 text-xs text-tinta-vermelha">{{ form.errors.texto }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="variavel in VARIAVEIS" :key="variavel.token" type="button" class="botao-secundario text-xs" :title="variavel.legenda" @click="inserir(variavel.token)">
                                {{ variavel.token }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-medium text-grafite/75">Prévia (com dados de exemplo)</p>
                        <p class="border border-linha bg-papel p-3 text-sm whitespace-pre-wrap">{{ previa }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.padrao" type="checkbox"> Usar como modelo padrão
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button class="botao" :disabled="form.processing">{{ modelo.id === 'novo' ? 'Cadastrar modelo' : 'Salvar modelo' }}</button>
                        <button type="button" class="botao-secundario" @click="editando = null">Cancelar</button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">
                            {{ modelo.nome }}
                            <span v-if="modelo.padrao" class="ml-1 font-estreita text-xs text-grafite/70">(padrão)</span>
                        </p>
                        <p class="line-clamp-1 font-estreita text-sm text-grafite/80">{{ modelo.texto }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="botao-secundario" @click="abrir(modelo)">Editar</button>
                        <button v-if="confirmando !== modelo.id" type="button" class="botao-perigo" @click="confirmando = modelo.id">Excluir</button>
                        <template v-else>
                            <button type="button" class="botao-perigo-confirmar" @click="excluir(modelo.id)">Confirmar exclusão</button>
                            <button type="button" class="botao-secundario" @click="confirmando = null">Manter modelo</button>
                        </template>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-grafite/80">Nenhum modelo cadastrado — a janela de envio sugere um texto.</p>

        <div v-if="editando !== 'novo'">
            <button type="button" class="botao-secundario" @click="abrir()">Novo modelo</button>
        </div>
    </section>
</template>
