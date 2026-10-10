<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ clientes: { type: Array, required: true } });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <p v-if="!clientes.length" class="border border-linha bg-white px-4 py-3 text-sm">
            Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="cliente in clientes" :key="cliente.id">
                <Link :href="`/clientes/${cliente.id}/edit`" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ cliente.nome }}</span>
                    <span class="font-estreita text-sm tabular-nums text-grafite/80">{{ cliente.documento }}</span>
                    <span class="w-full font-estreita text-xs text-grafite/70">{{ cliente.cidade || 'Sem cidade' }} · {{ cliente.modelo }}</span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
