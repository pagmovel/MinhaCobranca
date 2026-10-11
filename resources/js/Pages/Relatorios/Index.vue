<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

const relatorios = [
    { tipo: 'faturamento', titulo: 'Faturamento por período', descricao: 'Mês a mês: previsto, recebido e o que ficou em atraso.' },
    { tipo: 'clientes', titulo: 'Receita por cliente', descricao: 'Quanto cada cliente pagou, contratos ativos, valor médio e atrasos.' },
    { tipo: 'produtos', titulo: 'Receita por produto ou serviço', descricao: 'O que mais fatura, pelos itens das cobranças.' },
    { tipo: 'a-receber', titulo: 'Contas a receber', descricao: 'Pendentes por cliente: vencidas e o que vence em 30, 60 e 90 dias.' },
];
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
