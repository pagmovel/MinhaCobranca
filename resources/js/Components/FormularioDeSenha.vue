<script setup>
import { computed, nextTick, reactive, ref, useId } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Campo from './Campo.vue';
import { CAMPOS_DA_SENHA, MENSAGEM_DE_FALHA, mensagemDaFalhaHttp, primeiroCampoComErro, validarCamposDeSenha } from '../senha.js';

/**
 * Formulário "Alterar senha", dentro do popup `ModalAlterarSenha.vue`. Erro aparece junto do campo e o foco vai ao
 * primeiro campo com erro; os campos só são limpos no sucesso (a senha nunca volta do servidor, então apagar no erro
 * obrigaria a digitar tudo de novo). No sucesso a tela troca os campos pela confirmação e pelo botão "Fechar", que
 * recebe o foco. A senha só passa pelo `router.put`: não vai para `console`, `localStorage` nem props. Quem fecha o
 * popup chama `limpar()` (exposto), que apaga estado e DOM na hora: a janela leva ~200 ms para sair da tela e o que foi
 * digitado não pode ficar nela.
 */
const emit = defineEmits(['alterada', 'fechar']);

const pagina = usePage();
const email = computed(() => pagina.props.auth.user?.email ?? '');

const base = useId();
const idDoCampo = (nome) => `${base}-${nome}`;

const campos = [
    { nome: 'senha_atual', rotulo: 'Senha atual', autocomplete: 'current-password' },
    { nome: 'nova_senha', rotulo: 'Nova senha (mínimo de 8 caracteres)', autocomplete: 'new-password' },
    { nome: 'nova_senha_confirmation', rotulo: 'Repita a nova senha', autocomplete: 'new-password' },
];

const mostrar = ref(false);
const enviando = ref(false);
const valores = reactive({ senha_atual: '', nova_senha: '', nova_senha_confirmation: '' });
const erros = ref({});
const aviso = ref(''); // limite de tentativas, erro de servidor ou de rede
const sucesso = ref(false);
const botaoFechar = ref(null);

function focarPrimeiroComErro() {
    const campo = primeiroCampoComErro(erros.value);
    if (campo) {
        nextTick(() => document.getElementById(idDoCampo(campo))?.focus());
    }
}

function digitou(nome) {
    sucesso.value = false;
    aviso.value = '';
    if (erros.value[nome]) {
        erros.value = { ...erros.value, [nome]: undefined };
    }
}

function apagarCampos() {
    Object.keys(valores).forEach((nome) => { valores[nome] = ''; });
    mostrar.value = false;
}

/** Apaga as senhas do estado e do DOM. O DOM é direto: a janela que está saindo não reage mais ao estado. */
function limpar() {
    apagarCampos();
    CAMPOS_DA_SENHA.forEach((nome) => {
        const entrada = document.getElementById(idDoCampo(nome));
        if (entrada) {
            entrada.value = '';
            entrada.type = 'password';
        }
    });
}

defineExpose({ limpar });

function enviar() {
    if (enviando.value) {
        return;
    }
    sucesso.value = false;
    aviso.value = '';
    erros.value = validarCamposDeSenha(valores);
    if (primeiroCampoComErro(erros.value)) {
        focarPrimeiroComErro();

        return;
    }

    enviando.value = true;
    router.put('/perfil/senha', { ...valores }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            apagarCampos();
            sucesso.value = true;
            emit('alterada');
            // Os campos e o "Salvar" saem da tela; sem isto o foco cairia no `body`.
            nextTick(() => botaoFechar.value?.focus());
        },
        onError: (erro) => {
            erros.value = erro;
            focarPrimeiroComErro();
        },
        // false: sem o modal de erro do Inertia (429, 500...); a mensagem fica no próprio formulário.
        onHttpException: (resposta) => {
            aviso.value = mensagemDaFalhaHttp(resposta.status);

            return false;
        },
        onNetworkError: () => {
            aviso.value = MENSAGEM_DE_FALHA;

            return false;
        },
        onFinish: () => { enviando.value = false; },
    });
}
</script>

<template>
    <form class="flex flex-col gap-4" novalidate @submit.prevent="enviar">
        <!-- Só para o gerenciador de senhas do navegador saber de qual conta é a senha nova. -->
        <input type="text" autocomplete="username" :value="email" readonly tabindex="-1" aria-hidden="true" class="sr-only">

        <template v-if="!sucesso">
            <Campo v-for="campo in campos" :id="idDoCampo(campo.nome)" :key="campo.nome" :rotulo="campo.rotulo" :erro="erros[campo.nome] ?? ''">
                <input
                    :id="idDoCampo(campo.nome)"
                    v-model="valores[campo.nome]"
                    :type="mostrar ? 'text' : 'password'"
                    :name="campo.nome"
                    :autocomplete="campo.autocomplete"
                    :autofocus="campo.nome === 'senha_atual' ? true : undefined"
                    class="entrada"
                    :class="erros[campo.nome] ? 'border-tinta-vermelha' : ''"
                    :aria-invalid="erros[campo.nome] ? 'true' : undefined"
                    :aria-describedby="erros[campo.nome] ? `${idDoCampo(campo.nome)}-erro` : undefined"
                    autocapitalize="off"
                    spellcheck="false"
                    @input="digitou(campo.nome)"
                >
            </Campo>

            <button type="button" class="botao-secundario self-start px-3 py-1 text-xs" @click="mostrar = !mostrar">
                {{ mostrar ? 'Ocultar senhas' : 'Mostrar senhas' }}
            </button>
        </template>

        <p v-if="aviso" role="alert" class="border-l-4 border-tinta-vermelha pl-3 text-sm">{{ aviso }}</p>
        <div role="status">
            <p v-if="sucesso" class="border-l-4 border-tinta-verde pl-3 text-sm">Senha alterada.</p>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <button v-if="sucesso" ref="botaoFechar" type="button" class="botao" @click="emit('fechar')">Fechar</button>
            <template v-else>
                <button type="button" class="botao-secundario" @click="emit('fechar')">Cancelar</button>
                <!-- Sem `disabled` durante o envio: um botão desativado perde o foco e o teclado voltaria ao início da janela. -->
                <button type="submit" class="botao" :class="enviando ? 'opacity-50' : ''" :aria-disabled="enviando">
                    {{ enviando ? 'Salvando…' : 'Salvar nova senha' }}
                </button>
            </template>
        </div>
    </form>
</template>
