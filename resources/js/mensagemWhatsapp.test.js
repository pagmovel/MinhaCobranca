import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MENSAGEM_SUGERIDA, mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from './mensagemWhatsapp.js';

const dados = (extra) => ({
    cliente: 'Padaria do João',
    itens: [{ nome: 'Hospedagem de site', valorCentavos: 8990 }],
    vencimentos: ['2026-10-15'],
    totalCentavos: 8990,
    prestador: 'Marcos Ronaldo',
    ...extra,
});

test('substitui todas as variáveis conhecidas', () => {
    const texto = montarMensagem('Olá {cliente}! {itens} Vence em {vencimento}. Total: {total}. — {prestador}', dados());
    assert.equal(texto, 'Olá Padaria do João! - Hospedagem de site: R$ 89,90 Vence em 15/10/2026. Total: R$ 89,90. — Marcos Ronaldo');
});

test('variável desconhecida fica literal no texto', () => {
    const texto = montarMensagem('Oi {cliente}, {algumacoisa} aqui.', dados());
    assert.equal(texto, 'Oi Padaria do João, {algumacoisa} aqui.');
});

test('cobrança sem itens usa a descricao (primeira linha) ou "Serviço"', () => {
    const texto = montarMensagem('{itens}', dados({ itens: [{ nome: 'Serviço', valorCentavos: 10000 }] }));
    assert.equal(texto, '- Serviço: R$ 100,00');
});

test('mais de um item: uma linha por item', () => {
    const texto = montarMensagem('{itens}', dados({
        itens: [{ nome: 'Hospedagem', valorCentavos: 8990 }, { nome: 'Domínio', valorCentavos: 4000 }],
    }));
    assert.equal(texto, '- Hospedagem: R$ 89,90\n- Domínio: R$ 40,00');
});

test('duas datas: "e" antes da última, só a última com o ano', () => {
    assert.equal(montarMensagem('{vencimento}', dados({ vencimentos: ['2026-10-15', '2026-10-20'] })), '15/10 e 20/10/2026');
});

test('três datas ou mais: vírgula entre as primeiras, "e" antes da última', () => {
    assert.equal(
        montarMensagem('{vencimento}', dados({ vencimentos: ['2026-10-10', '2026-10-15', '2026-10-20'] })),
        '10/10, 15/10 e 20/10/2026',
    );
});

test('numeroWhatsapp: celular válido (11 dígitos, 9 depois do DDD)', () => {
    assert.equal(numeroWhatsapp('(79) 99999-0000'), '5579999990000');
});

test('numeroWhatsapp: fixo (10 dígitos) não é válido', () => {
    assert.equal(numeroWhatsapp('(79) 3214-0000'), null);
});

test('numeroWhatsapp: vazio, incompleto ou sem o 9 depois do DDD não é válido', () => {
    assert.equal(numeroWhatsapp(''), null);
    assert.equal(numeroWhatsapp('(79) 9999-000'), null);
    assert.equal(numeroWhatsapp('79812340000'), null); // 3º dígito não é 9
});

test('montarLinkWaMe: número com DDI e texto codificado', () => {
    const link = montarLinkWaMe('5579999990000', 'Olá! Tudo bem?');
    assert.equal(link, 'https://wa.me/5579999990000?text=Ol%C3%A1!%20Tudo%20bem%3F');
});

test('mensagemMuitoLonga: true a partir de 1000 caracteres', () => {
    assert.equal(mensagemMuitoLonga('a'.repeat(999)), false);
    assert.equal(mensagemMuitoLonga('a'.repeat(1001)), true);
});

test('{item}: nome puro, sem "-" nem valor, para usar em frase corrida', () => {
    const texto = montarMensagem('vence a hospedagem do domínio {item}.', dados({
        itens: [{ nome: 'exemplo.com.br', valorCentavos: 8990 }],
    }));
    assert.equal(texto, 'vence a hospedagem do domínio exemplo.com.br.');
});

test('{item}: mais de um item junta com "e" (mesmo padrão de {vencimento})', () => {
    const texto = montarMensagem('{item}', dados({
        itens: [{ nome: 'Hospedagem', valorCentavos: 8990 }, { nome: 'Domínio', valorCentavos: 4000 }],
    }));
    assert.equal(texto, 'Hospedagem e Domínio');
});

test('{item}: três ou mais junta com vírgula e "e" antes do último', () => {
    const texto = montarMensagem('{item}', dados({
        itens: [{ nome: 'A', valorCentavos: 100 }, { nome: 'B', valorCentavos: 200 }, { nome: 'C', valorCentavos: 300 }],
    }));
    assert.equal(texto, 'A, B e C');
});

test('{chave_pix}: substitui pela chave formatada', () => {
    const texto = montarMensagem('Chave: {chave_pix}', dados({ chavePix: 'cobranca@paguemovel.net.br' }));
    assert.equal(texto, 'Chave: cobranca@paguemovel.net.br');
});

test('{chave_pix}: sem chave fornecida, fica vazia (não trava nem mostra undefined)', () => {
    const texto = montarMensagem('Chave: {chave_pix}.', dados());
    assert.equal(texto, 'Chave: .');
});

test('{contato}: substitui pela pessoa de contato quando cadastrada', () => {
    const texto = montarMensagem('Oi, {contato}!', dados({ contato: 'Wendel' }));
    assert.equal(texto, 'Oi, Wendel!');
});

test('{contato}: sem pessoa de contato cadastrada, fica vazia (decisão do usuário: não cai no nome do cliente)', () => {
    const texto = montarMensagem('Oi, {contato}!', dados());
    assert.equal(texto, 'Oi, !');
});

test('{apelido}: substitui pelo apelido quando cadastrado', () => {
    assert.equal(montarMensagem('{apelido}', dados({ apelido: 'Padaria' })), 'Padaria');
});

test('{apelido}: sem apelido cadastrado, fica vazio', () => {
    assert.equal(montarMensagem('[{apelido}]', dados()), '[]');
});

test('{razao_social}: substitui pelo nome oficial do cliente', () => {
    assert.equal(montarMensagem('{razao_social}', dados({ razaoSocial: 'Padaria do João LTDA' })), 'Padaria do João LTDA');
});

test('cascata {a|b|c}: usa a primeira variável preenchida, na ordem escrita', () => {
    const texto = montarMensagem('Oi, {contato|apelido|razao_social}!', dados({
        contato: 'Wendel', apelido: 'Padaria', razaoSocial: 'Padaria do João LTDA',
    }));
    assert.equal(texto, 'Oi, Wendel!');
});

test('cascata {a|b|c}: pula as vazias até achar uma preenchida', () => {
    const texto = montarMensagem('Oi, {contato|apelido|razao_social}!', dados({ razaoSocial: 'Padaria do João LTDA' }));
    assert.equal(texto, 'Oi, Padaria do João LTDA!');
});

test('cascata {a|b|c}: todas vazias vira string vazia, nunca literal', () => {
    assert.equal(montarMensagem('Oi, {contato|apelido}!', dados()), 'Oi, !');
});

test('cascata com uma variável desconhecida no meio fica literal (erro de digitação não quebra nem oculta)', () => {
    const texto = montarMensagem('Oi, {contato|apelidoo}!', dados({ contato: 'Wendel' }));
    assert.equal(texto, 'Oi, {contato|apelidoo}!');
});

test('{saudacao}: antes de 12h vira "Bom dia"', () => {
    assert.equal(montarMensagem('{saudacao}', dados({ agora: new Date(2026, 9, 11, 8, 0) })), 'Bom dia');
});

test('{saudacao}: de 12h a 17h59 vira "Boa tarde"', () => {
    assert.equal(montarMensagem('{saudacao}', dados({ agora: new Date(2026, 9, 11, 12, 0) })), 'Boa tarde');
    assert.equal(montarMensagem('{saudacao}', dados({ agora: new Date(2026, 9, 11, 17, 59) })), 'Boa tarde');
});

test('{saudacao}: a partir de 18h vira "Boa noite"', () => {
    assert.equal(montarMensagem('{saudacao}', dados({ agora: new Date(2026, 9, 11, 18, 0) })), 'Boa noite');
    assert.equal(montarMensagem('{saudacao}', dados({ agora: new Date(2026, 9, 11, 23, 30) })), 'Boa noite');
});

test('MENSAGEM_SUGERIDA: itens em bloco, sem concordância verbal (achado da revisão, T24.9)', () => {
    const texto = montarMensagem(MENSAGEM_SUGERIDA, dados({
        itens: [{ nome: 'Hospedagem', valorCentavos: 8990 }, { nome: 'Domínio', valorCentavos: 4000 }],
        vencimentos: ['2026-10-15', '2026-10-20'],
        totalCentavos: 12990,
    }));
    assert.equal(
        texto,
        'Olá Padaria do João! Segue o lembrete de vencimento (15/10 e 20/10/2026):\n'
        + '- Hospedagem: R$ 89,90\n- Domínio: R$ 40,00\n\n'
        + 'Total: R$ 129,90\n\nQualquer dúvida, estou à disposição.\n\nMarcos Ronaldo',
    );
});
