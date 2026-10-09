<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaPublica from '../../Layouts/CamadaPublica.vue';
import Campo from '../../Components/Campo.vue';

const form = useForm({ email: '', password: '', lembrar: false });

const entrar = () => form.post('/entrar', { onFinish: () => form.reset('password') });
</script>

<template>
    <CamadaPublica titulo="Entrar">
        <form class="flex flex-col gap-4" @submit.prevent="entrar">
            <Campo id="email" rotulo="E-mail" :erro="form.errors.email">
                <input id="email" v-model="form.email" type="email" autocomplete="username" class="entrada" required>
            </Campo>
            <Campo id="password" rotulo="Senha" :erro="form.errors.password">
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" class="entrada" required>
            </Campo>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.lembrar" type="checkbox"> Manter conectado neste aparelho
            </label>
            <button class="botao" :disabled="form.processing">Entrar</button>
            <p class="text-sm">
                Ainda não tem conta?
                <Link href="/cadastro" class="font-semibold text-carbono underline">Criar conta</Link>
            </p>
        </form>
    </CamadaPublica>
</template>
