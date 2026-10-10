<script setup>
import { Link } from '@inertiajs/vue3';
import Campo from './Campo.vue';

defineProps({
    form: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    comLinhaDigitavel: { type: Boolean, default: false },
});
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

        <template v-if="form.forma_pagamento === 'pix'">
            <Campo v-if="chavesPix.length" id="chave_pix_id" rotulo="Chave PIX para receber" :erro="form.errors.chave_pix_id" class="sm:col-span-6">
                <select id="chave_pix_id" v-model="form.chave_pix_id" class="entrada" required>
                    <option value="" disabled>Escolha a chave</option>
                    <option v-for="chave in chavesPix" :key="chave.id" :value="chave.id">{{ chave.rotulo }}</option>
                </select>
            </Campo>
            <p v-else class="border border-linha bg-white px-4 py-3 text-sm sm:col-span-6">
                Você ainda não tem chave PIX. <Link href="/perfil#chaves-pix" class="font-semibold text-carbono underline">Cadastrar chave PIX</Link>
            </p>
        </template>

        <Campo
            v-if="comLinhaDigitavel && form.forma_pagamento === 'boleto'"
            id="linha_digitavel"
            rotulo="Linha digitável do boleto (opcional)"
            :erro="form.errors.linha_digitavel"
            class="sm:col-span-6"
        >
            <input id="linha_digitavel" v-model="form.linha_digitavel" v-mascara="'linhaDigitavel'" inputmode="numeric" class="entrada">
        </Campo>
    </fieldset>
</template>
