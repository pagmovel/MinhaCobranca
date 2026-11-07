import { itemBloqueado } from './progresso.js';

/**
 * Itens do menu principal, na ordem de sempre. "Usuários" só para administrador, com o contador de cadastros
 * aguardando liberação. O "Sair" não está aqui: fica no painel do usuário.
 */
export function itensDoMenu(progresso, administrador, pendentes) {
    return [
        { href: '/painel', rotulo: 'Painel' },
        { href: '/cobrancas', rotulo: 'Cobranças', bloqueado: itemBloqueado('/cobrancas', progresso) },
        { href: '/contratos', rotulo: 'Contratos', bloqueado: itemBloqueado('/contratos', progresso) },
        { href: '/clientes', rotulo: 'Clientes', bloqueado: itemBloqueado('/clientes', progresso) },
        { href: '/produtos-servicos', rotulo: 'Produtos e serviços', bloqueado: itemBloqueado('/produtos-servicos', progresso) },
        { href: '/relatorios', rotulo: 'Relatórios' },
        { href: '/auditoria', rotulo: 'Auditoria' },
        { href: '/perfil', rotulo: 'Meu perfil' },
        ...(administrador ? [{ href: '/admin/usuarios', rotulo: 'Usuários', contador: pendentes }] : []),
    ];
}

/** Texto do `title` do item bloqueado (🔒): diz o que falta cadastrar para liberar. */
export function explicacaoDoBloqueio(href) {
    return href === '/contratos' || href === '/cobrancas'
        ? 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.'
        : 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.';
}
