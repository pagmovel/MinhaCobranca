<script setup>
import { ref } from 'vue';
import FormularioDeSenha from './FormularioDeSenha.vue';
import Modal from './Modal.vue';

/**
 * Popup "Alterar senha", aberto pela linha "Alterar senha" do painel do usuário e por cima dele. Como há senha
 * digitada, o clique no fundo não fecha (o `Modal` já é assim por padrão); fecham o "✕", "Cancelar", "Fechar" (depois do
 * sucesso) e Esc. Qualquer jeito de fechar passa por `fechar()`, que apaga o que foi digitado antes de a janela sair.
 * Emite `alterada` quando o servidor aceita a nova senha, para o painel mostrar a confirmação ao lado da linha.
 */
defineProps({
    aberto: { type: Boolean, required: true },
});

const emit = defineEmits(['fechar', 'alterada']);

const formulario = ref(null);

function fechar() {
    formulario.value?.limpar();
    emit('fechar');
}
</script>

<template>
    <Modal :aberto="aberto" titulo="Alterar senha" largura="max-w-md" centralizado @fechar="fechar">
        <FormularioDeSenha ref="formulario" @alterada="emit('alterada')" @fechar="fechar" />
    </Modal>
</template>
