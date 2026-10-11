/** Mesmas contas do servidor (App\Support\ItensDeVenda), só para mostrar o total enquanto digita. */
export function milesimos(texto) {
    const partes = String(texto ?? '').trim().replace(',', '.').match(/^(\d+)(?:\.(\d{1,3}))?$/);

    return partes ? Number(partes[1]) * 1000 + Number((partes[2] ?? '').padEnd(3, '0')) : null;
}

/** "1.234,56" (máscara de valor) → 123456. */
export function centavos(texto) {
    const numeros = String(texto ?? '').replace(/\D/g, '');

    return numeros === '' ? null : Number(numeros);
}

/** BigInt: quantidade × preço passa do inteiro exato do JS (2^53) em valores grandes. */
export function totalDoItem(quantidade, preco) {
    const q = milesimos(quantidade);
    const p = centavos(preco);

    return q === null || p === null ? 0 : Number((BigInt(q) * BigInt(p) + 500n) / 1000n);
}

export function formatarCentavos(valor) {
    const texto = String(valor).padStart(3, '0');

    return `${texto.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${texto.slice(-2)}`;
}
