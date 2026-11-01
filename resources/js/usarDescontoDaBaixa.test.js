import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextTick, reactive } from 'vue';
import {
    TEXTO_INTEGRAL,
    calcularBaixa,
    camposAoTrocarAData,
    camposDaRegra,
    dataDaBaixaEmLote,
    paraOForm,
    pedidoDaCorrecao,
    previaDoLote,
    usarValoresDaBaixa,
} from './usarDescontoDaBaixa.js';

// Baixa com multa, juros e outros acréscimos (BaixaInline). Mesmos números do servidor (AcrescimosNaBaixaTest):
// R$ 210,00 vencidos em 05/01/2026, contrato com desconto de 15%, multa de 2% e juros de 1% ao mês; em 01/11/2026 são 300 dias.
const VENCIMENTO = '2026-01-05';
const HOJE = '2026-11-01';
const REGRAS = {
    desconto: { tipo: 'percentual', valor: 1500 },
    multa: { tipo: 'percentual', valor: 200 },
    juros: { tipo: 'percentual', valor: 100 },
};
const vazio = { tipo: 'percentual', numero: '' };

test('a regra do contrato preenche multa e juros com atraso, e o desconto sem atraso', () => {
    assert.deepEqual(camposDaRegra(VENCIMENTO, HOJE, REGRAS), {
        dias: 300,
        multa: { tipo: 'percentual', numero: '2' },
        juros: { tipo: 'percentual', numero: '1' },
        desconto: vazio,
    });
    // No próprio vencimento não há atraso: vale o desconto.
    assert.deepEqual(camposDaRegra(VENCIMENTO, VENCIMENTO, REGRAS), {
        dias: 0,
        multa: vazio,
        juros: vazio,
        desconto: { tipo: 'percentual', numero: '15' },
    });
});

test('regra em R$ vira o texto que se digita; sem regra, campos vazios', () => {
    const emReais = { multa: { tipo: 'valor', valor: 1000 }, juros: null, desconto: null };

    assert.deepEqual(camposDaRegra(VENCIMENTO, HOJE, emReais).multa, { tipo: 'valor', numero: '10,00' });
    assert.deepEqual(camposDaRegra(VENCIMENTO, HOJE, null), { dias: 300, multa: vazio, juros: vazio, desconto: vazio });
});

test('paraOForm devolve os nomes dos campos do formulário', () => {
    assert.deepEqual(paraOForm(camposDaRegra(VENCIMENTO, HOJE, REGRAS)), {
        multa_tipo: 'percentual', multa: '2', juros_tipo: 'percentual', juros: '1', desconto_tipo: 'percentual', desconto: '',
    });
});

test('trocar a data recalcula o que não foi mexido e mantém o que a pessoa alterou', () => {
    const anteriores = camposDaRegra(VENCIMENTO, HOJE, REGRAS);
    const novos = camposDaRegra(VENCIMENTO, VENCIMENTO, REGRAS);
    const atuais = { multa: { tipo: 'valor', numero: '10,00' }, juros: anteriores.juros, desconto: anteriores.desconto };

    assert.deepEqual(camposAoTrocarAData(atuais, anteriores, novos), {
        multa: { tipo: 'valor', numero: '10,00' },
        juros: vazio,
        desconto: { tipo: 'percentual', numero: '15' },
    });
});

test('o valor pago soma multa, juros e outros e tira o desconto; os números batem com o servidor', () => {
    const campos = { multa: { tipo: 'percentual', numero: '2' }, juros: { tipo: 'percentual', numero: '1' }, desconto: vazio, outros: '5,00' };

    assert.deepEqual(calcularBaixa(21000, VENCIMENTO, HOJE, campos), {
        dias: 300, descontoCentavos: 0, multaCentavos: 420, jurosCentavos: 2100, outrosCentavos: 500, pagoCentavos: 24020,
    });
    assert.equal(calcularBaixa(21000, VENCIMENTO, HOJE, { ...campos, juros: { tipo: 'valor', numero: '3,00' } }).jurosCentavos, 3000);
    assert.equal(calcularBaixa(21000, VENCIMENTO, '2026-01-06', campos).jurosCentavos, 7);
});

test('sem atraso multa, juros e outros valem zero, mesmo preenchidos', () => {
    const campos = {
        multa: { tipo: 'percentual', numero: '2' },
        juros: { tipo: 'valor', numero: '3,00' },
        desconto: { tipo: 'percentual', numero: '15' },
        outros: '5,00',
    };

    assert.deepEqual(calcularBaixa(21000, VENCIMENTO, VENCIMENTO, campos), {
        dias: 0, descontoCentavos: 3150, multaCentavos: 0, jurosCentavos: 0, outrosCentavos: 0, pagoCentavos: 17850,
    });
});

test('o desconto nunca passa do valor: R$ maior que a cobrança deixa o pago em zero, não negativo', () => {
    const campos = { multa: vazio, juros: vazio, desconto: { tipo: 'valor', numero: '300,00' }, outros: '' };

    assert.deepEqual(calcularBaixa(21000, VENCIMENTO, VENCIMENTO, campos), {
        dias: 0, descontoCentavos: 21000, multaCentavos: 0, jurosCentavos: 0, outrosCentavos: 0, pagoCentavos: 0,
    });
    // O desconto do contrato numa cobrança menor (R$ 100,00 com desconto de R$ 150,00) também fica no valor, com atraso ou não.
    const comAtraso = calcularBaixa(10000, VENCIMENTO, HOJE, { ...campos, desconto: { tipo: 'valor', numero: '150,00' }, outros: '5,00' });
    assert.deepEqual([comAtraso.descontoCentavos, comAtraso.pagoCentavos], [10000, 500]);
    // Dentro do limite, nada muda.
    assert.equal(calcularBaixa(21000, VENCIMENTO, VENCIMENTO, { ...campos, desconto: { tipo: 'valor', numero: '210,00' } }).pagoCentavos, 0);
    assert.equal(calcularBaixa(21000, VENCIMENTO, VENCIMENTO, { ...campos, desconto: { tipo: 'valor', numero: '10,00' } }).pagoCentavos, 20000);
});

test('texto inválido ou vírgula no fim: o inválido não soma, a vírgula conta como o número antes dela', () => {
    const base = { multa: vazio, juros: vazio, desconto: vazio, outros: '' };

    assert.equal(calcularBaixa(21000, VENCIMENTO, HOJE, { ...base, outros: 'abc' }).outrosCentavos, 0);
    assert.equal(calcularBaixa(21000, VENCIMENTO, HOJE, { ...base, outros: '100.000.000,00' }).outrosCentavos, 0);
    assert.equal(calcularBaixa(21000, VENCIMENTO, HOJE, { ...base, multa: { tipo: 'percentual', numero: '2,' } }).multaCentavos, 420);
});

/** O formulário da BaixaInline: os sete campos da baixa, a data e o que o `useForm` do Inertia oferece. */
function criarFormDaBaixa(pagoEm = HOJE, regras = REGRAS) {
    return reactive({
        pago_em: pagoEm,
        ...paraOForm(camposDaRegra(VENCIMENTO, pagoEm, regras)),
        outros: '',
        errors: {},
        clearErrors(...nomes) {
            nomes.forEach((nome) => delete this.errors[nome]);
        },
    });
}

function baixa(form, regras = REGRAS) {
    return usarValoresDaBaixa(form, { valorCentavos: () => 21000, vencimentoIso: () => VENCIMENTO, regras: () => regras });
}

test('a baixa abre com a regra do contrato e o valor pago ao vivo', () => {
    const form = criarFormDaBaixa();
    const { dias, valorPagoCentavos, calculados } = baixa(form);

    assert.equal(dias.value, 300);
    assert.equal(valorPagoCentavos.value, 23520);
    assert.deepEqual([calculados.value.multa, calculados.value.juros], [420, 2100]);
    form.outros = '5,00';
    assert.equal(valorPagoCentavos.value, 24020);
});

test('trocar a data recalcula os campos não editados e o editado fica; "Recalcular" volta à regra', async () => {
    const form = criarFormDaBaixa();
    const { recalcular, diferenteDaRegra } = baixa(form);

    assert.equal(diferenteDaRegra.value, false);
    form.multa_tipo = 'valor';
    form.multa = '10,00';
    assert.equal(diferenteDaRegra.value, true);

    form.pago_em = VENCIMENTO;
    await nextTick();
    assert.deepEqual([form.multa_tipo, form.multa], ['valor', '10,00']);
    assert.deepEqual([form.juros, form.desconto_tipo, form.desconto], ['', 'percentual', '15']);

    recalcular();
    assert.deepEqual([form.multa_tipo, form.multa], ['percentual', '']);
    assert.equal(form.desconto, '15');
    assert.equal(diferenteDaRegra.value, false);
});

test('campo que a pessoa esvaziou continua vazio ao trocar a data', async () => {
    const form = criarFormDaBaixa();

    baixa(form);
    form.juros = '';
    form.pago_em = '2026-11-02';
    await nextTick();
    assert.equal(form.juros, '');
    assert.equal(form.multa, '2');
});

test('o campo mostra a etiqueta "contrato" enquanto é o da regra', () => {
    const form = criarFormDaBaixa();
    const { etiqueta } = baixa(form);

    assert.equal(etiqueta('multa'), 'contrato');
    assert.equal(etiqueta('desconto'), '');
    form.multa = '3';
    assert.equal(etiqueta('multa'), '');
});

test('sem regra os campos vêm vazios, sem etiqueta e sem "Recalcular"', () => {
    const form = criarFormDaBaixa(HOJE, null);
    const { etiqueta, diferenteDaRegra, temRegra } = baixa(form, null);

    assert.equal(form.multa, '');
    assert.equal(etiqueta('multa'), '');
    assert.equal(temRegra.value, false);
    form.multa = '5';
    assert.equal(diferenteDaRegra.value, false);
});

test('desconto integral sem acréscimo anuncia; com acréscimo não', () => {
    const form = criarFormDaBaixa(VENCIMENTO);
    const { anuncio, descontoIntegral } = baixa(form);

    form.desconto = '100';
    assert.equal(descontoIntegral.value, true);
    assert.equal(anuncio.value, TEXTO_INTEGRAL);
    form.pago_em = HOJE;
    form.desconto_tipo = 'percentual';
    form.multa = '2';
    assert.equal(descontoIntegral.value, false);
});

test('digitar limpa o erro do campo e do tipo; o erro de outro campo fica', () => {
    const form = criarFormDaBaixa();
    form.errors = { multa: 'Percentual inválido.', multa_tipo: 'Tipo de multa inválido.', juros: 'x' };
    const { erroDo, limparErro } = baixa(form);

    assert.equal(erroDo('multa'), 'Percentual inválido.');
    limparErro('multa');
    assert.equal(erroDo('multa'), undefined);
    assert.equal(erroDo('juros'), 'x');
});

// Corrigir pagamento (Tarefa 5): o mesmo `usarValoresDaBaixa`, sem regra do contrato. O formulário abre com os valores gravados
// (R$ 210,00 pagos em 01/11/2026 com multa R$ 4,20, juros R$ 21,00 e outros R$ 5,00): multa e outros em R$ nos campos; os juros
// ficam em branco (o campo é "ao mês", o gravado é o total) e o gravado entra na conta. Trocar a data não recalcula nada.
function criarFormDaCorrecao(pagoEm = HOJE) {
    return reactive({
        pago_em: pagoEm,
        multa_tipo: 'valor', multa: '4,20',
        juros_tipo: 'percentual', juros: '',
        outros: '5,00',
        desconto_tipo: 'valor', desconto: '',
        errors: {},
        clearErrors() {},
    });
}

const correcao = (form) => usarValoresDaBaixa(form, {
    valorCentavos: () => 21000,
    vencimentoIso: () => VENCIMENTO,
    regras: () => null,
    jurosGravadoCentavos: () => 2100,
});

test('a correção abre com os gravados e o valor pago os soma', () => {
    const { dias, valorPagoCentavos, calculados, etiqueta, diferenteDaRegra } = correcao(criarFormDaCorrecao());

    assert.equal(dias.value, 300);
    assert.equal(valorPagoCentavos.value, 21000 + 420 + 2100 + 500);
    assert.deepEqual([calculados.value.multa, calculados.value.juros, calculados.value.outros], [420, 2100, 500]);
    assert.equal(etiqueta('multa'), '');
    assert.equal(diferenteDaRegra.value, false);
});

test('na correção, trocar a data não mexe nos campos nem nos gravados; sem atraso o valor pago deixa de somar os acréscimos', async () => {
    const form = criarFormDaCorrecao();
    const { dias, valorPagoCentavos } = correcao(form);

    // Os juros gravados não são recalculados pelos dias (o servidor os mantém).
    form.pago_em = '2026-02-01';
    await nextTick();
    assert.deepEqual([form.multa, form.juros, form.outros], ['4,20', '', '5,00']);
    assert.equal(valorPagoCentavos.value, 24020);

    // O servidor zera multa, juros e outros sem atraso (o que está digitado fica na tela, mas não conta).
    form.pago_em = VENCIMENTO;
    await nextTick();
    assert.equal(dias.value, 0);
    assert.deepEqual([form.multa, form.juros, form.outros], ['4,20', '', '5,00']);
    assert.equal(valorPagoCentavos.value, 21000);
});

test('na correção, juros digitados (ao mês) substituem o gravado e seguem os dias; 0 tira os juros', () => {
    const form = criarFormDaCorrecao();
    const { valorPagoCentavos, calculados } = correcao(form);

    form.juros_tipo = 'valor';
    form.juros = '3,00';
    assert.equal(calculados.value.juros, 3000);
    assert.equal(valorPagoCentavos.value, 21000 + 420 + 3000 + 500);
    form.juros = '0';
    assert.equal(calculados.value.juros, 0);
    assert.equal(valorPagoCentavos.value, 21000 + 420 + 500);
});

test('o pedido da correção só leva os juros quando o campo foi preenchido', () => {
    const base = { pago_em: HOJE, multa_tipo: 'valor', multa: '4,20', outros: '5,00', desconto_tipo: 'valor', desconto: '', motivo: 'x', senha: 'y' };
    const comJuros = { ...base, juros_tipo: 'valor', juros: '3,00' };

    assert.deepEqual(pedidoDaCorrecao({ ...base, juros_tipo: 'percentual', juros: '' }), base);
    assert.deepEqual(pedidoDaCorrecao({ ...base, juros_tipo: 'percentual', juros: ' ,' }), base);
    assert.deepEqual(pedidoDaCorrecao(comJuros), comJuros);
    assert.deepEqual(pedidoDaCorrecao({ ...base, juros_tipo: 'percentual', juros: '0' }), { ...base, juros_tipo: 'percentual', juros: '0' });
});

// Baixa em lote (Tarefa 4): os mesmos números de tests/Feature/Cobrancas/AcoesEmLoteTest.php, com o relógio em 01/11/2026.
// A (R$ 210,00, contrato 15% / multa 2% / juros 1%) e B (R$ 100,00, multa R$ 5,00 / juros R$ 3,00 ao mês) venceram em 05/01/2026
// (300 dias); C é do contrato A e vence em 05/12/2026; D não tem contrato.
const REGRAS_B = { desconto: null, multa: { tipo: 'valor', valor: 500 }, juros: { tipo: 'valor', valor: 300 } };
const linha = (valorCentavos, vencimentoIso, regras) => ({ valorCentavos, vencimentoIso, regras });
const A = linha(21000, VENCIMENTO, REGRAS);
const B = linha(10000, VENCIMENTO, REGRAS_B);
const C = linha(21000, '2026-12-05', REGRAS);
const D = linha(5000, VENCIMENTO, null);
const hoje = (linhas) => linhas.map(() => HOJE);
const branco = { desconto: vazio, multa: vazio, juros: vazio, outros: '' };
const pt = (numero) => ({ tipo: 'percentual', numero });
const rs = (numero) => ({ tipo: 'valor', numero });
const prever = (linhas, campos, datas = hoje(linhas)) => previaDoLote(linhas, datas, campos);
const resumo = (linhaDaPrevia) => [linhaDaPrevia.descontoCentavos, linhaDaPrevia.acrescimosCentavos, linhaDaPrevia.pagoCentavos];

test('lote em branco: cada cobrança usa o próprio contrato, e os totais somam as linhas', () => {
    assert.deepEqual(prever([A, B, C], branco), {
        linhas: [
            { valorCentavos: 21000, acrescimosCentavos: 2520, descontoCentavos: 0, pagoCentavos: 23520 },
            { valorCentavos: 10000, acrescimosCentavos: 3500, descontoCentavos: 0, pagoCentavos: 13500 },
            // C ainda não venceu: sem multa nem juros, e recebe o desconto de 15% do contrato.
            { valorCentavos: 21000, acrescimosCentavos: 0, descontoCentavos: 3150, pagoCentavos: 17850 },
        ],
        totais: { valorCentavos: 52000, acrescimosCentavos: 6020, descontoCentavos: 3150, pagoCentavos: 54870 },
    });
});

test('lote preenchido: o digitado vale para todas, até para a que não tem contrato', () => {
    const campos = { desconto: pt('10'), multa: pt('3'), juros: rs('1,00'), outros: '5,00' };
    const { linhas } = prever([A, B, D], campos);

    assert.deepEqual(linhas.map(resumo), [[2100, 2130, 21030], [1000, 1800, 10800], [500, 1650, 6150]]);
});

test('lote: percentual é calculado em cada cobrança e R$ é o mesmo valor em todas', () => {
    const duas = [linha(10001, VENCIMENTO, null), linha(24757, VENCIMENTO, null)];

    assert.deepEqual(prever(duas, { ...branco, multa: pt('10'), juros: pt('1') }).linhas.map(resumo), [[0, 2000, 12001], [0, 4952, 29709]]);
    assert.deepEqual(prever(duas, { ...branco, multa: rs('5,00') }).linhas.map(resumo), [[0, 500, 10501], [0, 500, 25257]]);
    assert.equal(prever(duas, { ...branco, multa: pt('10') }).totais.acrescimosCentavos, 3476);
});

test('lote: 0 digitado zera a regra do contrato em todas; em branco (ou só espaço) não', () => {
    const zero = { desconto: pt('0'), multa: pt('0'), juros: rs('0'), outros: '0' };

    assert.deepEqual(prever([A, C], zero).linhas.map(resumo), [[0, 0, 21000], [0, 0, 21000]]);
    assert.deepEqual(prever([C], { ...branco, desconto: pt('  ') }).linhas.map(resumo), [[3150, 0, 17850]]);
    assert.deepEqual(prever([C], { ...branco, desconto: pt('') }).linhas.map(resumo), [[3150, 0, 17850]]);
});

test('lote: só a cobrança em atraso na data recebe multa, juros e outros', () => {
    const campos = { desconto: vazio, multa: rs('5,00'), juros: rs('3,00'), outros: '5,00' };
    const venceHoje = linha(21000, HOJE, null);
    const aVencer = linha(21000, '2026-12-05', null);

    assert.deepEqual(prever([D, venceHoje, aVencer], campos).linhas.map(resumo), [[0, 4000, 9000], [0, 0, 21000], [0, 0, 21000]]);
});

test('lote: a data de cada cobrança é a do vencimento, ou hoje para a que ainda não venceu (nunca no futuro)', () => {
    assert.equal(dataDaBaixaEmLote('vencimento', VENCIMENTO, HOJE), VENCIMENTO);
    assert.equal(dataDaBaixaEmLote('vencimento', '2026-12-05', HOJE), HOJE);
    assert.equal(dataDaBaixaEmLote('vencimento', HOJE, HOJE), HOJE);
    assert.equal(dataDaBaixaEmLote('hoje', VENCIMENTO, HOJE), HOJE);
    assert.equal(dataDaBaixaEmLote('hoje', '2026-12-05', HOJE), HOJE);
});

test('lote com a data de vencimento: sem multa nem juros (nem os digitados), e o desconto do contrato vale', () => {
    const datas = [A, B, C].map((cobranca) => dataDaBaixaEmLote('vencimento', cobranca.vencimentoIso, HOJE));
    const digitado = { desconto: vazio, multa: pt('5'), juros: pt('5'), outros: '9,00' };

    assert.deepEqual(prever([A, B, C], branco, datas).linhas.map(resumo), [[3150, 0, 17850], [0, 0, 10000], [3150, 0, 17850]]);
    assert.deepEqual(prever([A, B, C], digitado, datas).linhas.map(resumo), [[3150, 0, 17850], [0, 0, 10000], [3150, 0, 17850]]);
});

test('lote sem regra e sem valores sai igual ao de hoje', () => {
    assert.deepEqual(prever([D, linha(10001, '2026-12-05', null)], branco), {
        linhas: [
            { valorCentavos: 5000, acrescimosCentavos: 0, descontoCentavos: 0, pagoCentavos: 5000 },
            { valorCentavos: 10001, acrescimosCentavos: 0, descontoCentavos: 0, pagoCentavos: 10001 },
        ],
        totais: { valorCentavos: 15001, acrescimosCentavos: 0, descontoCentavos: 0, pagoCentavos: 15001 },
    });
});

test('lote: desconto em R$ acima do valor (regra do contrato numa cobrança menor) fica no valor, como o servidor', () => {
    const contrato = { desconto: { tipo: 'valor', valor: 30000 }, multa: null, juros: null };

    assert.deepEqual(prever([linha(20000, '2026-12-05', contrato)], branco).linhas.map(resumo), [[20000, 0, 0]]);
});

test('lote: 100% e R$ igual à menor cobrança deixam o valor pago em zero', () => {
    const duas = [linha(10001, VENCIMENTO, null), linha(24757, VENCIMENTO, null)];

    assert.deepEqual(prever(duas, { ...branco, desconto: pt('100') }).totais, { valorCentavos: 34758, acrescimosCentavos: 0, descontoCentavos: 34758, pagoCentavos: 0 });
    assert.deepEqual(prever(duas, { ...branco, desconto: rs('100,01') }).linhas.map(resumo), [[10001, 0, 0], [10001, 0, 14756]]);
});

test('lote: vírgula ou ponto dão o mesmo valor e a vírgula final é digitação em curso; texto inválido não soma (a tela não pisca)', () => {
    const duas = [linha(10001, VENCIMENTO, null), linha(24757, VENCIMENTO, null)];
    const esperado = prever(duas, { ...branco, multa: pt('12.5') });

    assert.deepEqual(prever(duas, { ...branco, multa: pt('12,5') }), esperado);
    assert.deepEqual(prever(duas, { ...branco, multa: pt('12,') }).totais, prever(duas, { ...branco, multa: pt('12') }).totais);
    // Inválido é "preenchido": não volta para o contrato (o servidor recusaria o lote).
    assert.deepEqual(prever([A], { ...branco, multa: pt('abc'), juros: rs('abc'), outros: 'abc' }).linhas.map(resumo), [[0, 0, 21000]]);
});

test('lote sem cobranças tem totais zerados', () => {
    assert.deepEqual(prever([], branco), { linhas: [], totais: { valorCentavos: 0, acrescimosCentavos: 0, descontoCentavos: 0, pagoCentavos: 0 } });
});
