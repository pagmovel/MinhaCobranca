<script setup>
/** Situação de cadastro (contrato, produto, usuário): selo reto. O carimbo girado fica para cobranças e recibos. */
defineProps({
    texto: { type: String, required: true },
    tom: { type: String, default: 'neutro' },
    // Compacta: cabe na altura de um rótulo de campo (ex.: "contrato" ao lado do rótulo), sem aumentar a linha.
    compacta: { type: Boolean, default: false },
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
    <span
        class="inline-block rounded-(--raio-controle) border font-estreita whitespace-nowrap"
        :class="[tons[tom], compacta ? 'px-1 text-[0.65rem] leading-none' : 'px-2 py-0.5 text-xs']"
    >{{ texto }}</span>
</template>
