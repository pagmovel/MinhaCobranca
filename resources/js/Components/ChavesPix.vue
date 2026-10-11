<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Campo from './Campo.vue';

const props = defineProps({
    chaves: { type: Array, required: true },
    tipos: { type: Array, required: true },
});

const mascaras = { cpf: 'cpf', cnpj: 'cnpj', telefone: 'telefone', email: 'minusculas', aleatoria: 'minusculas' };
const editando = ref(null); // id da chave em edição, 'nova' para inclusão
const confirmando = ref(null);
const form = useForm({ tipo: 'cpf', chave: '', apelido: '' });

/** A chave nova entra como uma linha a mais no fim da lista: o formulário aparece sempre onde a pessoa clicou. */
const linhas = computed(() => (editando.value === 'nova' ? [...props.chaves, { id: 'nova' }] : props.chaves));

async function abrir(chave = null) {
    form.clearErrors();
    form.tipo = chave?.tipo ?? 'cpf';
    form.chave = chave?.chave ?? '';
    form.apelido = chave?.apelido ?? '';
    confirmando.value = null;
    editando.value = chave ? chave.id : 'nova';
    await nextTick();
    const campo = document.getElementById('chave');
    campo?.scrollIntoView({ block: 'nearest' });
    campo?.focus();
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

        <TransitionGroup v-if="linhas.length" tag="ul" name="lista" class="divide-y divide-linha border border-linha bg-white">
            <li v-for="chave in linhas" :key="chave.id" :class="editando === chave.id ? 'bg-papel' : ''">
                <form
                    v-if="editando === chave.id"
                    class="grid gap-4 px-4 py-4 sm:grid-cols-6"
                    :aria-label="chave.id === 'nova' ? 'Nova chave PIX' : `Editar chave ${chave.apelido || chave.tipo_rotulo}`"
                    @submit.prevent="salvar"
                    @keydown.esc="editando = null"
                >
                    <p class="text-sm font-semibold sm:col-span-6">{{ chave.id === 'nova' ? 'Nova chave PIX' : 'Editando esta chave' }}</p>
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
                        <button class="botao" :disabled="form.processing">{{ chave.id === 'nova' ? 'Cadastrar chave' : 'Salvar chave' }}</button>
                        <button type="button" class="botao-secundario" @click="editando = null">Cancelar</button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-semibold">{{ chave.apelido || chave.tipo_rotulo }}</p>
                        <p class="font-estreita text-sm text-grafite/80">{{ chave.tipo_rotulo }}: {{ chave.chave }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="botao-secundario" @click="abrir(chave)">Editar</button>
                        <button v-if="confirmando !== chave.id" type="button" class="botao-perigo" @click="confirmando = chave.id">Excluir</button>
                        <template v-else>
                            <button type="button" class="botao-perigo-confirmar" @click="excluir(chave.id)">Confirmar exclusão</button>
                            <button type="button" class="botao-secundario" @click="confirmando = null">Manter chave</button>
                        </template>
                    </div>
                </div>
            </li>
        </TransitionGroup>
        <p v-else class="text-sm text-grafite/80">Nenhuma chave cadastrada.</p>

        <div v-if="editando !== 'nova'">
            <button type="button" class="botao-secundario" @click="abrir()">Nova chave PIX</button>
        </div>
    </section>
</template>
