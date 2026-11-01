import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextTick, reactive, ref } from 'vue';
import {
    TEXTO_INTEGRAL,
    calcularBaixa,
    camposAoTrocarAData,
    camposDaRegra,
    paraOForm,
    usarDescontoDaBaixa,
    usarValoresDaBaixa,
} from './usarDescontoDaBaixa.js';

/** O que o `useForm` do Inertia oferece e a lógica usa: os dois campos, `errors` e `clearErrors`. */
function criarForm(campos = {}) {
    return reactive({
        desconto_tipo: 'percentual',
        desconto: '',
        errors: {},
        clearErrors(...nomes) {
            nomes.forEach((nome) => delete this.errors[nome]);
        },
        ...campos,
    });
}

test('o valor pago acompanha o desconto em % e em R$', async () => {
    const form = criarForm();
    const { valorPagoCentavos } = usarDescontoDaBaixa(form, () => 24757);

    assert.equal(valorPagoCentavos.value, 24757);
    form.desconto = '10';
    assert.equal(valorPagoCentavos.value, 22281);
    form.desconto_tipo = 'valor';
    await nextTick();
    form.desconto = '24,76';
    assert.equal(valorPagoCentavos.value, 22281);
});

test('a vírgula no fim conta como o número antes dela (o valor pago não pisca)', () => {
    const form = criarForm({ desconto: '12,' });
    const { valorPagoCentavos } = usarDescontoDaBaixa(form, () => 24757);

    form.desconto = '12';
    const comNumero = valorPagoCentavos.value;
    form.desconto = '12,';
    assert.equal(valorPagoCentavos.value, comNumero);
});

test('desconto de 100% é integral e anuncia; cobrança de valor 0 não é', () => {
    const valor = ref(24757);
    const form = criarForm({ desconto: '100' });
    const { descontoIntegral, anuncio } = usarDescontoDaBaixa(form, () => valor.value);

    assert.equal(descontoIntegral.value, true);
    assert.equal(anuncio.value, TEXTO_INTEGRAL);
    valor.value = 0;
    assert.equal(descontoIntegral.value, false);
    assert.equal(anuncio.value, '');
});

test('em % o número trava em 100 e avisa; em R$ trava no valor da cobrança', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const form = criarForm();
    const { aviso } = usarDescontoDaBaixa(form, () => 24757);

    form.desconto = '150';
    await nextTick();
    assert.equal(form.desconto, '100');
    assert.equal(aviso.value, 'Máximo: 100%');

    form.desconto_tipo = 'valor';
    await nextTick();
    form.desconto = '300,00';
    await nextTick();
    assert.equal(form.desconto, '247,57');
    assert.equal(aviso.value, 'Máximo: R$ 247,57');
});

// Lote: o valor de referência é o da menor cobrança e o aviso diz isso.
test('com o complemento, o aviso do máximo em R$ cita a menor cobrança; em % continua igual', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const form = criarForm({ desconto_tipo: 'valor' });
    const { aviso } = usarDescontoDaBaixa(form, () => 10001, { complementoDoMaximo: ' (valor da menor cobrança)' });

    form.desconto = '150,00';
    await nextTick();
    assert.equal(form.desconto, '100,01');
    assert.equal(aviso.value, 'Máximo: R$ 100,01 (valor da menor cobrança)');

    form.desconto_tipo = 'percentual';
    await nextTick();
    form.desconto = '150';
    await nextTick();
    assert.equal(form.desconto, '100');
    assert.equal(aviso.value, 'Máximo: 100%');
});

test('dentro do limite o número fica como digitado, sem aviso', async () => {
    const form = criarForm();
    const { aviso } = usarDescontoDaBaixa(form, () => 24757);

    form.desconto = '99,5';
    await nextTick();
    assert.equal(form.desconto, '99,5');
    assert.equal(aviso.value, '');
});

test('trocar % ↔ R$ limpa o número e o aviso (10% não vira R$ 0,10)', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const form = criarForm();
    const { aviso } = usarDescontoDaBaixa(form, () => 24757);

    form.desconto = '150';
    await nextTick();
    assert.equal(aviso.value, 'Máximo: 100%');

    form.desconto_tipo = 'valor';
    await nextTick();
    assert.equal(form.desconto, '');
    assert.equal(aviso.value, '');
});

test('digitar limpa o erro que o servidor deu no desconto e no tipo', async () => {
    const form = criarForm();
    form.errors = { desconto: 'Desconto inválido.', desconto_tipo: 'Tipo de desconto inválido.', pago_em: 'x' };
    const { erroDoDesconto } = usarDescontoDaBaixa(form, () => 24757);

    assert.equal(erroDoDesconto.value, 'Desconto inválido.');
    form.desconto = '5';
    await nextTick();
    assert.equal(erroDoDesconto.value, undefined);
    assert.equal(form.errors.pago_em, 'x');
});

test('o valor da cobrança é lido na hora (a prop pode mudar)', () => {
    const valor = ref(24757);
    const form = criarForm({ desconto: '10' });
    const { valorPagoCentavos } = usarDescontoDaBaixa(form, () => valor.value);

    assert.equal(valorPagoCentavos.value, 22281);
    valor.value = 10001;
    assert.equal(valorPagoCentavos.value, 9001);
});

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
