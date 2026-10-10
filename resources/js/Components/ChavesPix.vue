<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Campo from './Campo.vue';

defineProps({
    chaves: { type: Array, required: true },
    tipos: { type: Array, required: true },
});

const mascaras = { cpf: 'cpf', cnpj: 'cnpj', telefone: 'telefone', email: 'minusculas', aleatoria: 'minusculas' };
const editando = ref(null); // id da chave em edição, 'nova' para inclusão
const confirmando = ref(null);
const form = useForm({ tipo: 'cpf', chave: '', apelido: '' });

function abrir(chave = null) {
    form.clearErrors();
    form.tipo = chave?.tipo ?? 'cpf';
    form.chave = chave?.chave ?? '';
    form.apelido = chave?.apelido ?? '';
    editando.value = chave ? chave.id : 'nova';
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'nova') {
        form.post('/perfil/chaves-pix', opcoes);
    } else {
        form.put(`/perfil/chaves-pix/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/chaves-pix/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});
</script>

<template>
    <section id="chaves-pix" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-chaves-pix">
        <h2 id="titulo-chaves-pix" class="text-sm font-semibold">Chaves PIX</h2>
        <p class="text-sm text-grafite/80">As chaves aparecem para escolha nos contratos e cobranças; o recibo mostra o QR Code da chave escolhida.</p>

        <ul v-if="chaves.length" class="divide-y divide-linha border border-linha bg-white">
            <li v-for="chave in chaves" :key="chave.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-semibold">{{ chave.apelido || chave.tipo_rotulo }}</p>
                    <p class="font-estreita text-sm text-grafite/80">{{ chave.tipo_rotulo }} · {{ chave.chave }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="botao-secundario" @click="abrir(chave)">Editar</button>
                    <button v-if="confirmando !== chave.id" type="button" class="botao-secundario" @click="confirmando = chave.id">Excluir</button>
                    <template v-else>
                        <button type="button" class="botao-secundario border-tinta-vermelha text-tinta-vermelha" @click="excluir(chave.id)">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmando = null">Manter chave</button>
                    </template>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-grafite/80">Nenhuma chave cadastrada.</p>

        <form v-if="editando" class="grid gap-4 border border-linha bg-white p-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="tipo_chave" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="tipo_chave" v-model="form.tipo" class="entrada">
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="chave" rotulo="Chave" :erro="form.errors.chave" class="sm:col-span-4">
                <input id="chave" v-model="form.chave" v-mascara="mascaras[form.tipo]" class="entrada" required>
            </Campo>
            <Campo id="apelido" rotulo="Apelido (opcional, ex.: Nubank PJ)" :erro="form.errors.apelido" class="sm:col-span-6">
                <input id="apelido" v-model="form.apelido" maxlength="60" class="entrada">
            </Campo>
            <div class="flex flex-wrap gap-2 sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ editando === 'nova' ? 'Cadastrar chave' : 'Salvar chave' }}</button>
                <button type="button" class="botao-secundario" @click="editando = null">Voltar</button>
            </div>
        </form>
        <div v-else>
            <button type="button" class="botao-secundario" @click="abrir()">Nova chave PIX</button>
        </div>
    </section>
</template>
