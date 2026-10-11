/**
 * Apelido do cliente na descrição do contrato/cobrança (decisão do usuário, 2026-10-11): o recibo não mostra o apelido;
 * quem quiser o põe na primeira linha da descrição, por um botão, e pode editar ou apagar antes de salvar.
 */

/** Apelido do cliente escolhido no formulário (formato da autosugestão: `nome` = apelido quando há `nome_oficial`). */
export function apelidoDoCliente(cliente, clienteId) {
    if (!cliente?.nome_oficial || String(cliente.id) !== String(clienteId)) return '';

    return cliente.nome;
}

/** Apelido como primeira linha, antes do que já foi digitado. */
export function comApelidoNaDescricao(descricao, apelido) {
    return descricao ? `${apelido}\n${descricao}` : apelido;
}

/** A primeira linha já é o apelido? (o botão some) */
export function descricaoComecaCom(descricao, apelido) {
    return (descricao ?? '').split('\n')[0].trim() === apelido;
}
