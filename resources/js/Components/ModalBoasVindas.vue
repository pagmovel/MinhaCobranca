<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';

const props = defineProps({
    progresso: { type: Object, required: true },
});

const tela = ref(1);

const pendentes = (bloco) => props.progresso.pendencias.filter((p) => p.bloco === bloco && !p.feito).map((p) => p.rotulo);

function concluir() {
    router.post('/perfil/apresentacao');
}
</script>

<template>
    <Modal :aberto="true" titulo="Bem-vindo" largura="max-w-lg" @fechar="concluir">
        <div v-if="tela === 1" class="flex flex-col gap-4 text-sm">
            <p>Bem-vindo! Pra organizar seus recibos, hoje falta completar alguns cadastros.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao-secundario" @click="concluir">Pular</button>
                <button type="button" class="botao" @click="tela = 2">Avançar</button>
            </div>
        </div>
        <div v-else-if="tela === 2" class="flex flex-col gap-4 text-sm">
            <p>Primeiro: seu perfil, uma chave PIX e um modelo de mensagem.</p>
            <ul class="list-disc pl-5">
                <li v-for="rotulo in pendentes('perfil')" :key="rotulo">{{ rotulo }}</li>
            </ul>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao-secundario" @click="concluir">Pular</button>
                <button type="button" class="botao" @click="tela = 3">Avançar</button>
            </div>
        </div>
        <div v-else class="flex flex-col gap-4 text-sm">
            <p>Depois: produtos/serviços e clientes. Só então Contratos e Cobranças ficam liberados.</p>
            <ul class="list-disc pl-5">
                <li v-for="rotulo in pendentes('catalogo')" :key="rotulo">{{ rotulo }}</li>
            </ul>
            <div class="flex justify-end">
                <button type="button" class="botao" @click="concluir">Entendi, vamos lá</button>
            </div>
        </div>
    </Modal>
</template>
