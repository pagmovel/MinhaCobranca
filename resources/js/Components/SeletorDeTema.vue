<script setup>
import { ref, useId } from 'vue';
import { usarTema } from '../usarTema.js';
import MiniaturaDoTema from './MiniaturaDoTema.vue';

/**
 * Seção "Aparência" do painel do usuário: um grupo de rádio com um cartão por tema. Rádio nativo: as setas movem a
 * escolha como em qualquer grupo de rádio. Escolher aplica o tema na hora (cores, menu e Painel) e grava por baixo,
 * sem recarregar a página e sem apagar o que estiver digitado. Fica escondido enquanto houver um tema só.
 */
const { atual, lista, trocar } = usarTema();

const base = useId();
const idTitulo = `${base}-titulo`;
const nomeDoGrupo = `${base}-tema`;

const falha = ref(null); // id do tema que não chegou a ser gravado
const fontesDeReserva = ref(false);

async function escolher(id) {
    falha.value = null;
    fontesDeReserva.value = false;
    const resultado = await trocar(id);

    if (resultado.substituida) {
        return; // uma escolha mais nova tomou o lugar: não há erro a mostrar
    }
    if (!resultado.ok) {
        falha.value = id;

        return;
    }
    fontesDeReserva.value = resultado.fontes === 'reserva';
}
</script>

<template>
    <section v-if="lista.length > 1" class="px-6 py-5">
        <h3 :id="idTitulo" class="font-titulo text-base font-semibold">Aparência</h3>
        <p class="mt-1 text-sm text-grafite/70">A mudança vale na hora e fica salva na sua conta.</p>

        <div role="radiogroup" :aria-labelledby="idTitulo" class="mt-4 flex flex-col gap-3">
            <label
                v-for="tema in lista"
                :key="tema.id"
                class="grid cursor-pointer grid-cols-[7.5rem_minmax(0,1fr)] items-start gap-4 rounded-(--raio-controle) border border-linha bg-superficie p-3 transition-colors hover:border-grafite/40 has-[:checked]:border-carbono has-[:checked]:bg-carbono/5 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-carbono"
            >
                <input
                    type="radio"
                    class="sr-only"
                    :name="nomeDoGrupo"
                    :value="tema.id"
                    :checked="tema.id === atual?.id"
                    @change="escolher(tema.id)"
                >
                <span class="block overflow-hidden rounded-[calc(var(--raio-controle)+2px)] border border-linha">
                    <MiniaturaDoTema :tema="tema" />
                </span>
                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-titulo text-base font-semibold">{{ tema.nome }}</span>
                        <span
                            v-if="tema.id === atual?.id"
                            class="inline-flex items-center gap-1 rounded-full bg-carbono px-2 py-0.5 text-xs font-semibold text-sobre-acao"
                        >
                            <svg aria-hidden="true" width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 5.2l2.1 2.1L8 3" /></svg>
                            Atual
                        </span>
                    </span>
                    <span class="mt-1 block text-sm leading-snug text-grafite/70">{{ tema.descricao }}</span>
                </span>
            </label>
        </div>

        <div v-if="falha" role="alert" class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 border-l-4 border-tinta-vermelha pl-3 text-sm">
            <span>Não foi possível trocar o tema.</span>
            <button type="button" class="botao-secundario px-3 py-1 text-xs" @click="escolher(falha)">Tentar de novo</button>
        </div>
        <div role="status">
            <p v-if="fontesDeReserva" class="mt-4 border-l-4 border-tinta-laranja pl-3 text-sm">
                As fontes deste tema ainda não carregaram. Enquanto isso, o sistema usa as de reserva.
            </p>
        </div>
    </section>
</template>
