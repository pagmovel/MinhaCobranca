import { formatadores } from './mascaras.js';

/** Mesmas contas do servidor (App\Support\Desconto), só para mostrar e travar o desconto enquanto digita. */
const PERCENTUAL = 'percentual';
const VALOR = 'valor';
const CEM_POR_CENTO = 10000; // em pontos-base (% × 100)

/** Como o trim() do PHP: só espaços ASCII (o do JS também tira o NBSP, e o servidor trataria a entrada de outro jeito). */
const aparar = (texto) => String(texto ?? '').replace(/^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g, '');

/** "12,5" ou "12.5" → 1250; null fora do formato (até 3 dígitos inteiros e 2 decimais, sem sinal). */
function pontosBase(texto) {
    const partes = texto.match(/^(\d{1,3})(?:[.,](\d{1,2}))?$/);

    return partes ? Number(partes[1]) * 100 + Number((partes[2] ?? '').padEnd(2, '0')) : null;
}

/** Mesma leitura de App\Support\Dinheiro::paraCentavos: "1.234,56", "260,6", "R$ 260". */
function reais(texto) {
    let limpo = texto.replace(/R\$|[  ]/g, '');
    if (limpo.includes(',')) {
        limpo = limpo.replace(/\./g, '').replace(/,/g, '.');
    } else if (/^\d{1,3}(\.\d{3})+$/.test(limpo)) {
        limpo = limpo.replace(/\./g, '');
    }
    const partes = limpo.match(/^(\d+)(?:\.(\d{1,2}))?$/);

    return partes ? Number(partes[1]) * 100 + Number((partes[2] ?? '').padEnd(2, '0')) : null;
}

/** Vazio é 0, formato inválido é null. Não limita ao valor (ver travarDesconto). */
export function descontoEmCentavos(valorCentavos, tipo, entrada) {
    const texto = aparar(entrada);

    if (texto === '') {
        return 0;
    }
    if (tipo === VALOR) {
        return reais(texto);
    }
    const pontos = tipo === PERCENTUAL ? pontosBase(texto) : null;

    // BigInt, como em itens.js: conta inteira exata, meio centavo para cima, sem divisão em ponto flutuante.
    return pontos === null ? null : Number((BigInt(valorCentavos) * BigInt(pontos) + 5000n) / 10000n);
}

/**
 * Prévia da baixa em lote: o mesmo percentual em cada valor, arredondado por cobrança (como o servidor grava),
 * e os totais somam as linhas. A vírgula final ("12,") é digitação em curso: conta como o número antes dela.
 */
export function previaDoLote(valoresCentavos, entrada) {
    const linhas = valoresCentavos.map((valor) => {
        const desconto = descontoEmCentavos(valor, PERCENTUAL, String(entrada ?? '').replace(/[.,]$/, '')) ?? 0;

        return { valor, desconto, pago: valor - desconto };
    });
    const somar = (campo) => linhas.reduce((total, linha) => total + linha[campo], 0);

    return { linhas, totais: { valor: somar('valor'), desconto: somar('desconto'), pago: somar('pago') } };
}

/** O desconto nunca passa de 100% do valor: acima disso a entrada vira o limite. */
export function travarDesconto(valorCentavos, tipo, entrada) {
    const texto = aparar(entrada);

    // null (formato ainda incompleto ou inválido) nunca é maior que o limite.
    if (tipo === PERCENTUAL && pontosBase(texto) > CEM_POR_CENTO) {
        return { entrada: '100', travou: true };
    }
    if (tipo === VALOR && reais(texto) > valorCentavos) {
        return { entrada: formatadores.valor(String(valorCentavos)), travou: true };
    }

    return { entrada, travou: false };
}
