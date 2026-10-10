<script setup>
defineProps({
    id: { type: String, required: true },
    rotulo: { type: String, required: true },
    erro: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="font-estreita text-xs font-medium text-grafite/75">{{ rotulo }}</label>
        <slot />
        <p v-if="erro" :id="`${id}-erro`" class="text-xs text-tinta-vermelha">{{ erro }}</p>
    </div>
</template>
