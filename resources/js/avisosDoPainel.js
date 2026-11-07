/**
 * Os avisos do topo do Painel dos temas novos (Régua e Almanaque): perfil incompleto, vencimento em dia não útil e
 * contratos perto do fim. Cada um tem o texto, o rótulo do link e o destino; vem vazio quando nada precisa de atenção.
 */
export function avisosDoPainel({ perfilCompleto, vencimentosEmDiaNaoUtil, contratosParaRenovar }) {
    return [
        !perfilCompleto && { texto: 'Complete seu perfil para emitir recibos.', acao: 'Completar perfil', href: '/perfil' },
        vencimentosEmDiaNaoUtil > 0 && {
            texto: `${vencimentosEmDiaNaoUtil === 1 ? '1 cobrança em aberto vence' : `${vencimentosEmDiaNaoUtil} cobranças em aberto vencem`} em sábado, domingo ou feriado.`,
            acao: 'Ver e ajustar',
            href: '/perfil#feriados',
        },
        contratosParaRenovar > 0 && {
            texto: `${contratosParaRenovar === 1 ? '1 contrato encerra' : `${contratosParaRenovar} contratos encerram`} nos próximos dias.`,
            acao: 'Ver contratos',
            href: '/contratos',
        },
    ].filter(Boolean);
}
