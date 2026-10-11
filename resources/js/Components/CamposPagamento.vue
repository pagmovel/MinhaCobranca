<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Campo from './Campo.vue';
import CadastroRapidoChavePix from './CadastroRapidoChavePix.vue';

const props = defineProps({
    form: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    comLinhaDigitavel: { type: Boolean, default: false },
});

const page = usePage();
// Cópia local: a chave cadastrada no modal aparece e já fica escolhida, sem recarregar a página.
const chaves = ref([...props.chavesPix]);
const cadastroAberto = ref(false);

watch(() => props.chavesPix, (novas) => {
    chaves.value = [...novas];
});

function chaveCriada(chave) {
    chaves.value.push(chave);
    props.form.chave_pix_id = chave.id;
    router.reload({ only: ['chavesPix'] }); // as outras linhas da tela (lista de cobranças) também passam a vê-la
}
</script>

<template>
    <fieldset class="grid gap-4 sm:col-span-6 sm:grid-cols-6">
        <legend class="mb-2 text-sm font-semibold">Forma de pagamento</legend>
        <div class="flex flex-wrap gap-4 sm:col-span-6">
            <label v-for="forma in formasPagamento" :key="forma.valor" class="flex items-center gap-2 text-sm">
                <input v-model="form.forma_pagamento" type="radio" name="forma_pagamento" :value="forma.valor"> {{ forma.rotulo }}
            </label>
        </div>
        <p v-if="form.errors.forma_pagamento" class="text-xs text-tinta-vermelha sm:col-span-6">{{ form.errors.forma_pagamento }}</p>

        <Transition name="surgir">
            <div v-if="form.forma_pagamento === 'pix'" class="grid gap-2 sm:col-span-6">
                <Campo v-if="chaves.length" id="chave_pix_id" rotulo="Chave PIX para receber" :erro="form.errors.chave_pix_id">
                    <select id="chave_pix_id" v-model="form.chave_pix_id" class="entrada" required>
                        <option value="" disabled>Escolha a chave</option>
                        <option v-for="chave in chaves" :key="chave.id" :value="chave.id">{{ chave.rotulo }}</option>
                    </select>
                </Campo>
                <p v-else class="text-sm text-grafite/80">Você ainda não tem chave PIX cadastrada.</p>
                <div>
                    <button type="button" class="botao-secundario px-3 py-1" @click="cadastroAberto = true">+ Nova chave PIX</button>
                </div>
            </div>
        </Transition>

        <Transition name="surgir">
            <Campo
                v-if="comLinhaDigitavel && form.forma_pagamento === 'boleto'"
                id="linha_digitavel"
                rotulo="Linha digitável do boleto (opcional)"
                :erro="form.errors.linha_digitavel"
                class="sm:col-span-6"
            >
                <input id="linha_digitavel" v-model="form.linha_digitavel" v-mascara="'linhaDigitavel'" inputmode="numeric" class="entrada">
            </Campo>
        </Transition>

        <CadastroRapidoChavePix
            :aberto="cadastroAberto"
            :tipos="page.props.tiposChavePix ?? []"
            @fechar="cadastroAberto = false"
            @criada="chaveCriada"
        />
    </fieldset>
</template>
