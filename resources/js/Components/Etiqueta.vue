<script setup>
/** Situação de cadastro (contrato, produto, usuário): selo reto. O carimbo girado fica para cobranças e recibos. */
defineProps({
    texto: { type: String, required: true },
    tom: { type: String, default: 'neutro' },
});

const tons = {
    neutro: 'border-carbono text-carbono',
    verde: 'border-tinta-verde text-tinta-verde',
    laranja: 'border-tinta-laranja text-tinta-laranja',
    vermelho: 'border-tinta-vermelha text-tinta-vermelha',
    apagado: 'border-grafite/40 text-grafite/60',
};
</script>

<template>
    <span class="inline-block rounded-sm border px-2 py-0.5 font-estreita text-xs whitespace-nowrap" :class="tons[tom]">{{ texto }}</span>
</template>
