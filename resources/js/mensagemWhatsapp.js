import { formatarCentavos } from './itens.js';

/** Limite prático observado em clientes do WhatsApp: acima disso, alguns cortam a mensagem. */
const LIMITE_PRATICO = 1000;

/** Usado quando não há modelo cadastrado nem padrão. Em bloco (não numa frase), para não exigir concordância verbal
 * com a quantidade de itens — "estám vencendo" era o bug corrigido (achado da revisão, T24.9). */
export const MENSAGEM_SUGERIDA = 'Olá {cliente}! Segue o lembrete de vencimento ({vencimento}):\n{itens}\n\n'
    + 'Total: {total} {condicao}\n\nQualquer dúvida, estou à disposição.\n\n{prestador}';

/** Legenda das variáveis (tela de modelos). Teste: toda variável daqui é reconhecida por `montarMensagem`. */
export const VARIAVEIS = [
    { token: '{saudacao}', legenda: 'Bom dia / Boa tarde / Boa noite, pela hora de quem envia' },
    { token: '{cliente}', legenda: 'nome do cliente (apelido, senão o nome)' },
    { token: '{contato}', legenda: 'pessoa de contato (vazio se não houver uma cadastrada)' },
    { token: '{apelido}', legenda: 'só o apelido (vazio se não houver um cadastrado)' },
    { token: '{razao_social}', legenda: 'nome oficial do cliente' },
    {
        token: '{contato|apelido|razao_social}',
        legenda: 'o nome mais informal disponível: contato, senão apelido, senão razão social',
    },
    { token: '{item}', legenda: 'nome do(s) item(ns), em frase' },
    { token: '{itens}', legenda: 'lista dos itens vencendo, com o valor de cada um' },
    { token: '{vencimento}', legenda: 'data(s) de vencimento' },
    { token: '{total}', legenda: 'valor a pagar hoje (com o desconto, a multa e os juros do contrato)' },
    {
        token: '{condicao}',
        legenda: 'a condição do contrato em uma frase, como no recibo (vazia sem regra no contrato)',
    },
    { token: '{chave_pix}', legenda: 'sua chave PIX (quando a cobrança usa PIX)' },
    { token: '{prestador}', legenda: 'seu nome ou razão social' },
];

function linhaDoItem(item) {
    return `- ${item.nome}: R$ ${formatarCentavos(item.valorCentavos)}`;
}

/** "2026-10-15" (ISO, sem hora) → "15/10/2026" ou "15/10" sem o ano. */
function formatarData(iso, comAno) {
    const [ano, mes, dia] = iso.split('-');

    return comAno ? `${dia}/${mes}/${ano}` : `${dia}/${mes}`;
}

/** Uma string: ela mesma. Duas ou mais: "a, b e c". */
function juntarComE(lista) {
    if (lista.length === 1) {
        return lista[0];
    }

    return `${lista.slice(0, -1).join(', ')} e ${lista.at(-1)}`;
}

/** Uma data: dia/mês/ano. Duas ou mais: "a, b e c" — só a última com o ano. */
function juntarVencimentos(vencimentos) {
    if (vencimentos.length === 1) {
        return formatarData(vencimentos[0], true);
    }
    const curtas = vencimentos.slice(0, -1).map((data) => formatarData(data, false));

    return juntarComE([...curtas, formatarData(vencimentos.at(-1), true)]);
}

/** "Bom dia" até 11h59, "Boa tarde" até 17h59, "Boa noite" daí em diante — hora de quem está montando a mensagem. */
function saudacao(data) {
    const hora = data.getHours();
    if (hora < 12) {
        return 'Bom dia';
    }

    return hora < 18 ? 'Boa tarde' : 'Boa noite';
}

/** Nome de uma variável, ou várias separadas por "|" numa cascata: {contato|apelido|razao_social}. */
const PADRAO_VARIAVEL = /\{([a-z_]+(?:\|[a-z_]+)*)\}/gi;

/**
 * Substitui as variáveis conhecidas por valores reais; o que não reconhece fica literal no texto (erro de
 * digitação no modelo não pode travar nem sumir pedaço da mensagem). Uma chave pode trazer várias variáveis
 * separadas por "|" (cascata): usa a primeira da lista que não estiver vazia — ex. {contato|apelido|razao_social}
 * manda o nome mais informal disponível. Se QUALQUER uma das opções da cascata não for reconhecida, a chave
 * inteira fica literal (mesma regra de erro de digitação); se todas forem reconhecidas mas vazias, vira "".
 */
export function montarMensagem(texto, {
    cliente, itens, vencimentos, totalCentavos, condicao, prestador, chavePix, contato, apelido, razaoSocial, agora,
}) {
    const valores = {
        cliente,
        contato: contato ?? '',
        apelido: apelido ?? '',
        razao_social: razaoSocial ?? '',
        // Nomes em frase corrida ("vence a hospedagem do domínio {item}"), sem "-" nem valor — diferente de {itens}.
        item: juntarComE(itens.map((item) => item.nome)),
        itens: itens.map(linhaDoItem).join('\n'),
        vencimento: juntarVencimentos(vencimentos),
        total: `R$ ${formatarCentavos(totalCentavos)}`,
        condicao: condicao ?? '',
        chave_pix: chavePix ?? '',
        saudacao: saudacao(agora ?? new Date()),
        prestador,
    };

    // {condicao} vazia leva embora o espaço que a separava do texto de antes ("Total: {total} {condicao}"): sem sobra.
    const modelo = valores.condicao === '' ? texto.replaceAll(' {condicao}', '{condicao}') : texto;

    return modelo.replace(PADRAO_VARIAVEL, (trecho, grupo) => {
        const opcoes = grupo.split('|');
        if (!opcoes.every((opcao) => opcao in valores)) {
            return trecho;
        }

        return valores[opcoes.find((opcao) => valores[opcao] !== '') ?? opcoes.at(-1)];
    });
}

/** Celular válido (DDI 55 + DDD + 9 dígitos começando em "9") para o link do WhatsApp; senão, null. */
export function numeroWhatsapp(telefone) {
    const digitos = String(telefone ?? '').replace(/\D/g, '');

    return digitos.length === 11 && digitos[2] === '9' ? `55${digitos}` : null;
}

export function montarLinkWaMe(numero, texto) {
    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}

export function mensagemMuitoLonga(texto) {
    return texto.length > LIMITE_PRATICO;
}
