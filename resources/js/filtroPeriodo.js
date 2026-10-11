/** Meses entre dois valores "AAAA-MM" (b − a). */
const meses = (a, b) => (Number(b.slice(0, 4)) - Number(a.slice(0, 4))) * 12 + Number(b.slice(5, 7)) - Number(a.slice(5, 7));

/**
 * Novo valor do "Até" depois de mudar o mês no filtro de cobranças, para o servidor nunca recusar o período por um campo
 * que a pessoa não mexeu. "Até" acompanha o mês enquanto os dois eram iguais, nunca fica antes dele nem a 36 meses ou
 * mais (volta a um mês só); vazio vira o mês; mês vazio (o servidor usa o atual) leva o "Até" junto.
 */
export function ajustarAte(mesAnterior, mes, ate) {
    if (!mes) return '';
    if (!ate || ate === mesAnterior || ate < mes || meses(mes, ate) >= 36) return mes;
    return ate;
}

/** De `meses` atrás a `meses` à frente (AAAA-MM), para os links "Ver cobranças": com 12, são 24 meses (limite 36). */
export function periodoAoRedor(hoje = new Date(), meses = 12) {
    const mes = (deslocamento) => {
        const data = new Date(hoje.getFullYear(), hoje.getMonth() + deslocamento, 1);

        return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}`;
    };

    return { mes: mes(-meses), ate: mes(meses) };
}

/**
 * O ano do campo de data ou mês já foi digitado inteiro? O `change` do Chrome sai a cada dígito do ano (0002, 0020,
 * 0202, 2026): sem esta guarda, cada dígito virava um pedido ao servidor. Vazio passa (o servidor usa o padrão).
 */
export function anoCompleto(valor) {
    if (!valor) return true;
    const ano = valor.split('-')[0];

    return ano.length === 4 && ano >= '1900' && ano <= '2100';
}
