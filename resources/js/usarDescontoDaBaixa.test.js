import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nextTick, reactive, ref } from 'vue';
import { TEXTO_INTEGRAL, usarDescontoDaBaixa } from './usarDescontoDaBaixa.js';

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
