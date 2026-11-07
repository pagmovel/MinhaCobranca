import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Verificado no Bunny Fonts em 2026-10-09: Archivo e Archivo Narrow (estáticas).
                bunny('Archivo', { weights: [400, 500, 600, 700] }),
                bunny('Archivo Narrow', { weights: [500, 600] }),
                // Verificado no Bunny Fonts em 2026-11-07: estáticas só com o eixo de peso (o Bunny recusa `wdth`,
                // então o `font-stretch` dos protótipos Régua e Almanaque não existe). Peso inexistente derruba o build.
                bunny('Bricolage Grotesque', { weights: [700, 800] }), // Régua e Almanaque: títulos e números
                bunny('Figtree', { weights: [400, 500, 600, 700] }), // Régua: texto
                bunny('Onest', { weights: [400, 500, 600, 700] }), // Almanaque: texto
            ],
        }),
        vue({
            template: {
                // Caminho absoluto (ex.: src="/perfil/logo") é rota do Laravel, não arquivo para importar.
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
