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

// Ícones do menu (traços num quadro de 16x16, do protótipo do Almanaque); só os temas que mostram ícones os desenham.
const ICONES = {
    '/painel': 'M2 2h5v6H2zM9 2h5v3H9zM9 7h5v7H9zM2 10h5v4H2z',
    '/cobrancas': 'M3.5 1.5h9v13l-2.2-1.3-2.3 1.3-2.3-1.3-2.2 1.3zM6 5.5h4M6 8.5h4',
    '/contratos': 'M4 1.5h5.5l2.5 2.5v10.5H4zM9.5 1.5V4H12M6 8.5h4M6 11h4',
    '/clientes': 'M6 7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM1.5 14c0-2.6 2-4.5 4.5-4.5s4.5 1.9 4.5 4.5M10.5 2.3a2.5 2.5 0 0 1 0 4.6M12.5 9.8c1.3.6 2 2.1 2 4.2',
    '/produtos-servicos': 'M8 1.5l6 3v7l-6 3-6-3v-7zM2 4.5l6 3 6-3M8 7.5v7',
    '/relatorios': 'M2 14h12M4 11.5v-4M8 11.5V3M12 11.5V6',
    '/auditoria': 'M8 1.5l5.5 2v4c0 3.4-2.3 5.8-5.5 7-3.2-1.2-5.5-3.6-5.5-7v-4zM5.5 8l1.8 1.8L10.5 6.5',
    '/perfil': 'M8 7.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2.5 14.5c0-3 2.5-5 5.5-5s5.5 2 5.5 5',
    '/admin/usuarios': 'M5.5 7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM1 14c0-2.6 2-4.5 4.5-4.5S10 11.4 10 14M11 5.5h4M13 3.5v4',
};

/** Traço (atributo `d` de um SVG 16x16) do ícone do item do menu; destino sem ícone devolve vazio. */
export function iconeDoItem(href) {
    return ICONES[href] ?? '';
}

/** Texto do `title` do item bloqueado (🔒): diz o que falta cadastrar para liberar. */
export function explicacaoDoBloqueio(href) {
    return href === '/contratos' || href === '/cobrancas'
        ? 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.'
        : 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.';
}
