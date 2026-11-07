import { ref } from 'vue';

/**
 * Verdadeiro depois que o popup da novidade foi dispensado nesta carga da página. Vale mesmo se o `POST` que marca
 * como visto falhar: o popup nunca prende o usuário nem reabre a cada tela; volta no próximo carregamento completo.
 */
export const novidadeDispensada = ref(false);

/** Quem era o usuário da última visita (`null` = ninguém logado), para saber quando outra pessoa entra na mesma aba. */
const usuarioDaUltimaVisita = ref(null);

/**
 * Chamar a cada visita do Inertia com o id do usuário da página. Se mudou (sair e entrar na mesma aba), a dispensa do
 * outro usuário não vale para este: a novidade dele continua pendente no banco e o popup tem de aparecer.
 */
export function acompanharUsuarioDaNovidade(idDoUsuario, { dispensada = novidadeDispensada, ultimo = usuarioDaUltimaVisita } = {}) {
    if (ultimo.value !== idDoUsuario) {
        ultimo.value = idDoUsuario;
        dispensada.value = false;
    }
}

/**
 * Onde fica o botão do nome, para o texto do popup. A casca superior (Régua) e o celular (cabeçalho com o avatar ao
 * lado do "Menu") têm o botão no canto de cima, à direita; a casca lateral, em tela larga, no rodapé do menu.
 */
export function textoDoLugar(casca, celular = false) {
    return casca === 'superior' || celular ? 'no canto de cima, à direita' : 'no canto de baixo do menu';
}

/** Frase do popup: onde clicar e quantos temas existem. */
export function textoDaNovidade(casca, quantidade, celular = false) {
    return `Clique no seu nome, ${textoDoLugar(casca, celular)}, e escolha entre ${quantidade} temas. Também dá para alterar a senha ali.`;
}
