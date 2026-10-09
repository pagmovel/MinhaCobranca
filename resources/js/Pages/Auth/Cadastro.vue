<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaPublica from '../../Layouts/CamadaPublica.vue';
import Campo from '../../Components/Campo.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const criar = () => form.post('/cadastro', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <CamadaPublica titulo="Criar conta">
        <p class="mb-6 text-sm text-grafite/80">
            Depois do cadastro, o administrador libera seu acesso. Você recebe a confirmação ao tentar entrar.
        </p>
        <form class="flex flex-col gap-4" @submit.prevent="criar">
            <Campo id="name" rotulo="Nome completo" :erro="form.errors.name">
                <input id="name" v-model="form.name" autocomplete="name" class="entrada" required>
            </Campo>
            <Campo id="email" rotulo="E-mail" :erro="form.errors.email">
                <input id="email" v-model="form.email" type="email" autocomplete="email" class="entrada" required>
            </Campo>
            <Campo id="password" rotulo="Senha (mínimo 8 caracteres)" :erro="form.errors.password">
                <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="entrada" required>
            </Campo>
            <Campo id="password_confirmation" rotulo="Repita a senha">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="entrada" required>
            </Campo>
            <button class="botao" :disabled="form.processing">Criar conta</button>
            <p class="text-sm">
                Já tem conta? <Link href="/entrar" class="font-semibold text-carbono underline">Entrar</Link>
            </p>
        </form>
    </CamadaPublica>
</template>
