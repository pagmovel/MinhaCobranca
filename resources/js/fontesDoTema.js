import { buscarJson } from './http.js';

/** Quanto esperar as fontes baixarem antes de assumir que vão ficar nas de reserva (o tema já está aplicado). */
const LIMITE_EM_MS = 4000;

/**
 * Temas cujas fontes já estão no `<head>`: `familias` guarda o que `GET /perfil/tema/{id}/fontes` devolveu e
 * `confirmada` diz se o navegador já terminou de carregá-las. O tema com que a página foi aberta entra confirmado:
 * o Blade pôs as fontes dele no `<head>` e a própria página as usa.
 */
const temasNoHead = new Map();
const temaDaCarga = globalThis.document?.documentElement?.dataset?.tema;
if (temaDaCarga) {
    temasNoHead.set(temaDaCarga, { familias: {}, confirmada: true });
}

/** Põe no `<head>` só os `<link>` e `<style>` do HTML do servidor (nada de `<script>`), marcados com o tema. */
function inserirNoHead(html, id, documento) {
    const molde = documento.createElement('template');
    molde.innerHTML = html;
    for (const no of [...molde.content.children]) {
        if (no.tagName === 'LINK' || no.tagName === 'STYLE') {
            no.dataset.fontesDoTema = id;
            documento.head.append(no);
        }
    }
}

/** Espera todas as famílias, em todos os pesos do tema; `false` se alguma não existir, falhar ou passar do prazo. */
async function esperarAsFontes(familias, documento, limite) {
    const pedidos = Object.entries(familias).flatMap(([familia, pesos]) => pesos.map((peso) => documento.fonts.load(`${peso} 16px "${familia}"`)));
    // `load` devolve as faces carregadas: lista vazia = o `@font-face` não está na página.
    const todas = Promise.all(pedidos).then((faces) => faces.every((lista) => lista.length > 0), () => false);
    let temporizador;
    const prazo = new Promise((resolver) => { temporizador = setTimeout(() => resolver(false), limite); });

    try {
        return await Promise.race([todas, prazo]);
    } finally {
        clearTimeout(temporizador);
    }
}

/**
 * Deixa as fontes do tema `id` na página sem recarregar: busca o HTML do `@fonts` do tema, acrescenta ao `<head>`
 * **uma vez por tema** e espera as fontes carregarem. Resolve `'ok'` ou `'reserva'` (o tema vale do mesmo jeito, com as
 * fontes de reserva até o navegador conseguir); nunca rejeita. Uma segunda tentativa não duplica o HTML: só espera de novo.
 * shortcut: duas famílias iguais em dois temas (Bricolage na Régua e no Almanaque) entram duas vezes no `<head>`, com o
 * mesmo arquivo e o mesmo `@font-face`; o navegador baixa uma vez só. Filtrar se um dia pesar.
 */
export async function carregarFontesDoTema(id, {
    buscar = buscarJson,
    documento = globalThis.document,
    temas = temasNoHead,
    limite = LIMITE_EM_MS,
} = {}) {
    try {
        let tema = temas.get(id);
        if (tema?.confirmada) {
            return 'ok';
        }

        if (!tema) {
            const { dados } = await buscar(`/perfil/tema/${encodeURIComponent(id)}/fontes`);
            if (!dados?.familias) {
                return 'reserva';
            }
            inserirNoHead(String(dados.html ?? ''), id, documento);
            tema = { familias: dados.familias, confirmada: false };
            temas.set(id, tema);
        }

        tema.confirmada = await esperarAsFontes(tema.familias, documento, limite);

        return tema.confirmada ? 'ok' : 'reserva';
    } catch {
        return 'reserva';
    }
}
