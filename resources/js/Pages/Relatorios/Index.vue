<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import { RELATORIOS as relatorios } from '../../relatorios.js';
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Relatórios</h1>
        <ul class="divide-y divide-linha border border-linha bg-white">
            <li v-for="relatorio in relatorios" :key="relatorio.tipo">
                <Link :href="`/relatorios/${relatorio.tipo}`" class="block px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ relatorio.titulo }}</span>
                    <span class="block text-sm text-grafite/80">{{ relatorio.descricao }}</span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
