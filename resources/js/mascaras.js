const digitos = (texto) => String(texto ?? '').replace(/\D/g, '');

/** Preenche o molde: '0' e 'A' recebem um caractere; o resto é separador fixo. */
function aplicar(limpo, molde) {
    let saida = '';
    let indice = 0;

    for (const caractere of molde) {
        if (indice >= limpo.length) {
            break;
        }
        saida += caractere === '0' || caractere === 'A' ? limpo[indice++] : caractere;
    }

    return saida;
}

function cnpj(texto) {
    const limpo = String(texto ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);

    return aplicar(limpo, 'AA.AAA.AAA/AAAA-00');
}

/** Formatação enquanto digita. É conforto: o servidor normaliza e valida de novo. */
export const formatadores = {
    /** Dígitos entram pelos centavos: 2 → 0,02; 26060 → 260,60. */
    valor(texto) {
        const numeros = digitos(texto).replace(/^0+(?=\d)/, '');
        if (numeros === '') {
            return '';
        }
        const completo = numeros.padStart(3, '0');
        const inteiro = completo.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return `${inteiro},${completo.slice(-2)}`;
    },

    /** CPF enquanto tiver até 11 dígitos; depois CNPJ (alfanumérico desde jul/2026). */
    documento(texto) {
        const limpo = String(texto ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);

        return limpo.length <= 11 && /^\d*$/.test(limpo) ? aplicar(limpo, '000.000.000-00') : cnpj(limpo);
    },

    /** Valor colado ("1.500", "260,6", "1234.5") lido como o servidor lê (`Dinheiro::paraCentavos`). */
    valorColado(texto) {
        let limpo = String(texto ?? '').replace(/R\$|\s/g, '');
        if (limpo.includes(',')) {
            limpo = limpo.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(limpo)) {
            limpo = limpo.replace(/\./g, '');
        }
        const partes = limpo.match(/^(\d+)(?:\.(\d{1,2}))?$/);
        if (!partes) {
            return formatadores.valor(texto);
        }

        return formatadores.valor(partes[1] + (partes[2] ?? '').padEnd(2, '0'));
    },

    cpf: (texto) => aplicar(digitos(texto).slice(0, 11), '000.000.000-00'),

    cnpj,

    cep: (texto) => aplicar(digitos(texto).slice(0, 8), '00000-000'),

    telefone(texto) {
        let numeros = digitos(texto);
        // "+55 (91) 98765-4321": o DDI entraria como DDD e trocaria o número (chave PIX gravada errada).
        if (numeros.length > 11 && numeros.startsWith('55')) {
            numeros = numeros.slice(2);
        }
        numeros = numeros.slice(0, 11);

        return aplicar(numeros, numeros.length <= 10 ? '(00) 0000-0000' : '(00) 00000-0000');
    },

    /** Boleto bancário (47) ou arrecadação/convênio (48, começa com 8). */
    linhaDigitavel(texto) {
        const numeros = digitos(texto).slice(0, 48);

        return numeros.startsWith('8')
            ? aplicar(numeros, '00000000000-0 00000000000-0 00000000000-0 00000000000-0')
            : aplicar(numeros.slice(0, 47), '00000.00000 00000.000000 00000.000000 0 00000000000000');
    },

    minusculas: (texto) => String(texto ?? '').trim().toLowerCase(),
};
