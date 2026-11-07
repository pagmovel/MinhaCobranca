<script setup>
/**
 * Recibo em SVG (logo do sistema). Aparece só nos temas que definem `--logo-exibicao: block`; no Talão fica
 * escondido (`none`) e o menu continua só com o texto. A cor vem de `--color-menu-logo`.
 */
</script>

<template>
    <svg
        aria-hidden="true"
        width="30"
        height="30"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linejoin="round"
        stroke-linecap="round"
        class="flex-none text-menu-logo [display:var(--logo-exibicao,none)]"
    >
        <path d="M5 2.5h14v19l-2.3-1.6-2.4 1.6-2.3-1.6-2.3 1.6-2.4-1.6L5 21.5z" />
        <path d="M8.5 8h7M8.5 11.5h7M8.5 15h4" />
    </svg>
</template>
