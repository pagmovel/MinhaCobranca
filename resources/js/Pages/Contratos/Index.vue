<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ contratos: { type: Array, required: true } });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Contratos</h1>
            <Link href="/contratos/create" class="botao">Novo contrato</Link>
        </div>

        <p v-if="!contratos.length" class="border border-linha bg-white px-4 py-3 text-sm">
            Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="contrato in contratos" :key="contrato.id" :class="{ 'opacity-60': !contrato.ativo }">
                <Link :href="`/contratos/${contrato.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ contrato.cliente }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ contrato.valor }}</span>
                    <span class="text-sm text-grafite/80">{{ contrato.resumo }}</span>
                    <span class="font-estreita text-xs text-grafite/70 sm:text-right">
                        {{ contrato.periodicidade }}, vence dia {{ contrato.dia_vencimento }}{{ contrato.ativo ? '' : ' — encerrado' }}
                    </span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
