import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from './mensagemWhatsapp.js';

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
