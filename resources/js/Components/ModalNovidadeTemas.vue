<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { novidadeDispensada, textoDaNovidade } from '../novidadeDosTemas.js';
import MiniaturaDoTema from './MiniaturaDoTema.vue';
import Modal from './Modal.vue';

/**
 * Popup único para quem já usava o sistema quando os temas chegaram. Qualquer jeito de fechar (os dois botões, Esc,
 * "✕" e o fundo) marca como visto; se o `POST` falhar o popup fecha do mesmo jeito e volta no próximo carregamento.
 * Quem renderiza decide se ele aparece (`mostrarNovidadeTemas`, nunca junto da apresentação inicial) e o que fazer
 * depois: `escolher` abre o painel do usuário e `depois` faz o botão do nome pulsar.
 */
const props = defineProps({
    temas: { type: Array, required: true },
    casca: { type: String, required: true },
});

const emit = defineEmits(['escolher', 'depois']);

const celular = typeof matchMedia === 'function' && !matchMedia('(min-width: 768px)').matches;
const texto = computed(() => textoDaNovidade(props.casca, props.temas.length, celular));

function marcarComoVisto() {
    // Primeiro: quem renderiza tira o popup da tela por este valor, antes de agir sobre o `emit`.
    novidadeDispensada.value = true;
    router.post('/perfil/novidade-temas', {}, {
        preserveScroll: true,
        preserveState: true,
        // false: sem o modal de erro do Inertia; falhar aqui não pode atrapalhar quem só quer fechar o aviso.
        onHttpException: () => false,
        onNetworkError: () => false,
    });
}

function escolher() {
    marcarComoVisto();
    emit('escolher');
}

function depois() {
    marcarComoVisto();
    emit('depois');
}
</script>

<template>
    <Modal :aberto="true" titulo="Agora você escolhe a aparência do sistema" largura="max-w-xl" centralizado fechar-no-fundo @fechar="depois">
        <div class="flex flex-col gap-5 text-sm">
            <p class="leading-relaxed">{{ texto }}</p>

            <ul class="grid grid-cols-[repeat(auto-fit,minmax(5.5rem,1fr))] gap-3">
                <li v-for="tema in temas" :key="tema.id" class="flex flex-col gap-1.5">
                    <span class="block overflow-hidden rounded-[calc(var(--raio-controle)+2px)] border border-linha">
                        <MiniaturaDoTema :tema="tema" />
                    </span>
                    <span class="font-titulo font-semibold">{{ tema.nome }}</span>
                </li>
            </ul>

            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" class="botao-secundario" @click="depois">Agora não</button>
                <button type="button" class="botao" autofocus @click="escolher">Escolher meu tema agora</button>
            </div>
        </div>
    </Modal>
</template>
