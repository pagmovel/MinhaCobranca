<script setup>
import { computed, nextTick, reactive, ref, useId } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Campo from './Campo.vue';
import { MENSAGEM_DE_FALHA, mensagemDaFalhaHttp, primeiroCampoComErro, validarCamposDeSenha } from '../senha.js';

/**
 * Seção "Alterar senha" do painel do usuário. Erro aparece junto do campo e o foco vai ao primeiro campo com erro; os
 * campos só são limpos no sucesso (a senha nunca volta do servidor, então apagar no erro obrigaria a digitar tudo de
 * novo). A senha só passa pelo `router.put`: não vai para `console`, `localStorage` nem props. Fechar o painel
 * desmonta o formulário e descarta o que foi digitado, de propósito.
 */
const pagina = usePage();
const email = computed(() => pagina.props.auth.user?.email ?? '');

const base = useId();
const idCorpo = `${base}-corpo`;
const idDoCampo = (nome) => `${base}-${nome}`;

const campos = [
    { nome: 'senha_atual', rotulo: 'Senha atual', autocomplete: 'current-password' },
    { nome: 'nova_senha', rotulo: 'Nova senha (mínimo de 8 caracteres)', autocomplete: 'new-password' },
    { nome: 'nova_senha_confirmation', rotulo: 'Repita a nova senha', autocomplete: 'new-password' },
];

const aberta = ref(false);
const mostrar = ref(false);
const enviando = ref(false);
const valores = reactive({ senha_atual: '', nova_senha: '', nova_senha_confirmation: '' });
const erros = ref({});
const aviso = ref(''); // limite de tentativas, erro de servidor ou de rede
const sucesso = ref(false);

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

function limpar() {
    Object.keys(valores).forEach((nome) => { valores[nome] = ''; });
    mostrar.value = false;
}

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
            limpar();
            sucesso.value = true;
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
    <section class="px-6 py-5">
        <h3 class="font-titulo text-base font-semibold">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-carbono"
                :aria-expanded="aberta"
                :aria-controls="idCorpo"
                @click="aberta = !aberta"
            >
                Alterar senha
                <svg
                    aria-hidden="true"
                    width="12"
                    height="12"
                    viewBox="0 0 12 12"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="flex-none transition-transform"
                    :class="aberta ? 'rotate-180' : ''"
                >
                    <path d="M2.5 4.5L6 8l3.5-3.5" />
                </svg>
            </button>
        </h3>

        <Transition name="surgir">
            <form v-show="aberta" :id="idCorpo" class="mt-4 flex flex-col gap-4" novalidate @submit.prevent="enviar">
                <!-- Só para o gerenciador de senhas do navegador saber de qual conta é a senha nova. -->
                <input type="text" autocomplete="username" :value="email" readonly tabindex="-1" aria-hidden="true" class="sr-only">

                <Campo v-for="campo in campos" :id="idDoCampo(campo.nome)" :key="campo.nome" :rotulo="campo.rotulo" :erro="erros[campo.nome] ?? ''">
                    <input
                        :id="idDoCampo(campo.nome)"
                        v-model="valores[campo.nome]"
                        :type="mostrar ? 'text' : 'password'"
                        :name="campo.nome"
                        :autocomplete="campo.autocomplete"
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

                <p v-if="aviso" role="alert" class="border-l-4 border-tinta-vermelha pl-3 text-sm">{{ aviso }}</p>
                <div role="status">
                    <p v-if="sucesso" class="border-l-4 border-tinta-verde pl-3 text-sm">Senha alterada.</p>
                </div>

                <!-- Sem `disabled` durante o envio: um botão desativado perde o foco e o teclado voltaria ao início do painel. -->
                <button type="submit" class="botao w-full" :class="enviando ? 'opacity-50' : ''" :aria-disabled="enviando">
                    {{ enviando ? 'Salvando…' : 'Salvar nova senha' }}
                </button>
            </form>
        </Transition>
    </section>
</template>
