/** Ações de um usuário na lista do admin: a principal pela situação; "Bloquear" no menu, nunca na própria conta. */
export function acoesDoUsuario(usuario) {
    const principal = { pendente: 'aprovar', bloqueado: 'reativar' }[usuario.status] ?? null;

    return { principal, menu: usuario.status !== 'bloqueado' && !usuario.eu ? ['bloquear'] : [] };
}
