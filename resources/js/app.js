import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import mascara from './diretivas/mascara.js';
import IndicadorDeCarregamento from './Components/IndicadorDeCarregamento.vue';
import { pendentes, vigiarFetch } from './carregando.js';

// Indicador global (pedido do usuário, 2026-10-11): todo fetch e toda visita do Inertia acendem o "Carregando…".
vigiarFetch();
router.on('start', () => { pendentes.value++; });
router.on('finish', () => { pendentes.value = Math.max(0, pendentes.value - 1); });

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => [h(App, props), h(IndicadorDeCarregamento)] })
            .use(plugin)
            .directive('mascara', mascara)
            .mount(el);
    },
});
