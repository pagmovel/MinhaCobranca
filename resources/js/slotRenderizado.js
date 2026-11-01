import { Comment, Fragment } from 'vue';

/**
 * Um slot só tem conteúdo se renderizou algo: `<p v-if>` falso vira comentário e o id dele não existe no DOM.
 * Recebe o retorno de chamar o slot (lista de nós) ou `undefined` quando o pai não passou o slot.
 */
export const slotTemConteudo = (nos) => (nos ?? []).some(
    (no) => (no.type === Fragment ? slotTemConteudo(no.children) : no.type !== Comment),
);
