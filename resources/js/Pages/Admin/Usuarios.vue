<script setup>
import { router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ usuarios: { type: Array, required: true } });

const rotuloStatus = { pendente: 'Aguardando liberação', aprovado: 'Liberado', bloqueado: 'Bloqueado' };

const acao = (id, nome) => router.post(`/admin/usuarios/${id}/${nome}`, {}, { preserveScroll: true });
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Usuários</h1>
        <ul class="divide-y divide-linha border border-linha bg-white">
            <li v-for="usuario in usuarios" :key="usuario.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-semibold">{{ usuario.nome }} <span v-if="usuario.is_admin" class="font-estreita text-xs text-grafite/70">administrador</span></p>
                    <p class="text-sm text-grafite/80">{{ usuario.email }} · cadastro em {{ usuario.cadastrado_em }}</p>
                    <p class="font-estreita text-sm" :class="usuario.status === 'pendente' ? 'text-carbono' : usuario.status === 'bloqueado' ? 'text-tinta-vermelha' : 'text-tinta-verde'">
                        {{ rotuloStatus[usuario.status] }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button v-if="usuario.status === 'pendente'" type="button" class="botao" @click="acao(usuario.id, 'aprovar')">Liberar acesso</button>
                    <button v-if="usuario.status === 'bloqueado'" type="button" class="botao-secundario" @click="acao(usuario.id, 'aprovar')">Reativar</button>
                    <button v-if="usuario.status !== 'bloqueado'" type="button" class="botao-secundario" @click="acao(usuario.id, 'bloquear')">Bloquear</button>
                </div>
            </li>
        </ul>
    </CamadaApp>
</template>
