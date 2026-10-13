const BLOCOS_POR_HREF = {
    '/clientes': 'perfil',
    '/produtos-servicos': 'perfil',
    '/contratos': 'liberado',
    '/cobrancas': 'liberado',
};

/** Só os 4 itens de cadastro bloqueiam, e só pelo bloco que a Fase 25 exige para cada um. */
export function itemBloqueado(href, progresso) {
    if (!progresso) {
        return false;
    }
    const bloco = BLOCOS_POR_HREF[href];

    return bloco ? !progresso[bloco] : false;
}

// Mesmas mensagens de GarantirProgressoDeConfiguracao::MENSAGENS: quando o perfil é a causa raiz do bloqueio,
// orienta por ele em vez do catálogo (a sequência pede perfil primeiro).
const MENSAGENS = {
    perfil: 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.',
    liberado: 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.',
};

/** Mensagem a mostrar quando uma ação que cria contrato/cobrança é oferecida sem o bloco "liberado" completo. */
export function mensagemBloqueioDeCriacao(progresso) {
    return progresso.perfil ? MENSAGENS.liberado : MENSAGENS.perfil;
}
