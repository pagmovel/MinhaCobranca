import { computed, watch } from 'vue';
import { diasDeAtraso, entradaDaRegra, jurosEmCentavos, multaEmCentavos, regraDaEntrada, regraDoCampo } from './condicoes.js';
import { aparar, descontoEmCentavos, semSeparadorFinal } from './desconto.js';

export const TEXTO_INTEGRAL = 'Desconto integral: nada será recebido.';

// Baixa individual (BaixaInline) e correção do pagamento (CorrecaoPagamentoInline) com multa, juros e outros acréscimos.
// A trava e o aviso de máximo ficam no CampoValorOuPercentual; aqui só a conta, a regra do contrato e o que muda quando a
// data muda.

const NOMES = ['multa', 'juros', 'desconto'];
const SEM_REGRA = { tipo: 'percentual', numero: '' };
const igual = (a, b) => a.tipo === b.tipo && a.numero === b.numero;
/** Sem atraso a baixa só tem o desconto; multa, juros e outros ficam escondidos (e o servidor os zera). */
const visiveis = (dias) => (dias > 0 ? NOMES : ['desconto']);

/**
 * Os campos que a regra do contrato preenche na data do pagamento, como se digitam: multa e juros com atraso, o
 * desconto sem atraso. `regras` é `{ desconto, multa, juros }` (cada uma `{ tipo, valor }` ou null) ou null.
 */
export function camposDaRegra(vencimentoIso, dataIso, regras) {
    const dias = diasDeAtraso(vencimentoIso, dataIso);
    const doContrato = (regra, vale) => (vale && regra?.valor > 0 ? { tipo: regra.tipo, numero: entradaDaRegra(regra.tipo, regra.valor) } : SEM_REGRA);

    return {
        dias,
        multa: doContrato(regras?.multa, dias > 0),
        juros: doContrato(regras?.juros, dias > 0),
        desconto: doContrato(regras?.desconto, dias === 0),
    };
}

/** `{ multa: { tipo, numero } }` → `{ multa_tipo, multa }`: os nomes dos campos do formulário e do pedido. */
export const paraOForm = (campos) => Object.fromEntries(NOMES.flatMap((nome) => [[`${nome}_tipo`, campos[nome].tipo], [nome, campos[nome].numero]]));

const doForm = (form) => Object.fromEntries(NOMES.map((nome) => [nome, { tipo: form[`${nome}_tipo`], numero: form[nome] }]));

/** Os campos do formulário como `calcularBaixa` os lê: `{ multa, juros, desconto }` como `{ tipo, numero }` e `outros` em R$. */
export const camposDoForm = (form) => ({ ...doForm(form), outros: form.outros });

/** Depois de trocar a data: o campo que ainda era o da regra anterior passa para o da nova; o que a pessoa mexeu fica. */
export const camposAoTrocarAData = (atuais, anteriores, novos) => Object.fromEntries(
    NOMES.map((nome) => [nome, igual(atuais[nome], anteriores[nome]) ? novos[nome] : atuais[nome]]),
);

/**
 * A conta da baixa com o que está nos campos (`{ multa, juros, desconto }` como `{ tipo, numero }` e `outros` em R$), pelas
 * mesmas regras do servidor. Texto inválido vale 0; a vírgula no fim ("12,") conta como o número antes dela. Sem atraso,
 * multa, juros e outros valem 0. O desconto nunca passa do valor (o do contrato em R$ numa cobrança menor é limitado, como
 * no servidor), então o valor pago não fica negativo.
 */
export function calcularBaixa(valorCentavos, vencimentoIso, dataIso, campos) {
    const dias = diasDeAtraso(vencimentoIso, dataIso);
    const regra = (nome) => regraDoCampo(campos[nome].tipo, campos[nome].numero);
    const descontoCentavos = Math.min(valorCentavos, descontoEmCentavos(valorCentavos, campos.desconto.tipo, semSeparadorFinal(campos.desconto.numero)) ?? 0);
    const multaCentavos = dias > 0 ? multaEmCentavos(valorCentavos, regra('multa')?.tipo, regra('multa')?.valor) : 0;
    const jurosCentavos = dias > 0 ? jurosEmCentavos(valorCentavos, regra('juros')?.tipo, regra('juros')?.valor, dias) : 0;
    const outrosCentavos = dias > 0 ? (regraDaEntrada('valor', semSeparadorFinal(campos.outros)) ?? 0) : 0;

    return {
        dias,
        descontoCentavos,
        multaCentavos,
        jurosCentavos,
        outrosCentavos,
        pagoCentavos: valorCentavos - descontoCentavos + multaCentavos + jurosCentavos + outrosCentavos,
    };
}

/**
 * A data da baixa de cada cobrança no lote: hoje para todas (`hoje`) ou o vencimento de cada uma (`vencimento`), mas a que
 * ainda não venceu é baixada hoje, nunca no futuro. Como o servidor (`CobrancaController::pagarEmLote`).
 */
export const dataDaBaixaEmLote = (modo, vencimentoIso, hojeIso) => (modo === 'hoje' || vencimentoIso > hojeIso ? hojeIso : vencimentoIso);

/** Em branco (vazio, só espaço ou só a vírgula do fim) o lote usa o contrato de cada cobrança; qualquer número, inclusive 0, vale. */
const emBranco = (numero) => aparar(semSeparadorFinal(numero)) === '';

/**
 * Prévia da baixa em lote, pela mesma regra do servidor (`ValoresDaBaixa`, modo `lote`): o campo digitado no topo vale para
 * todas as cobranças (% calculado em cada uma, R$ igual em cada); em branco, cada uma usa o próprio contrato na data dela.
 * `linhas` são `{ valorCentavos, vencimentoIso, regras }`, `datas` a data da baixa de cada uma (ISO) e `campos` os do
 * formulário do topo (`{ multa, juros, desconto }` como `{ tipo, numero }` e `outros`). Multa, juros e outros só entram nas
 * que estão em atraso na data. Devolve por linha `{ valorCentavos, acrescimosCentavos, descontoCentavos, pagoCentavos }` e os
 * mesmos quatro nos totais, que somam as linhas.
 */
export function previaDoLote(linhas, datas, campos) {
    const calculadas = linhas.map(({ valorCentavos, vencimentoIso, regras }, i) => {
        const doContrato = camposDaRegra(vencimentoIso, datas[i], regras);
        const usados = { outros: campos.outros };
        NOMES.forEach((nome) => { usados[nome] = emBranco(campos[nome].numero) ? doContrato[nome] : campos[nome]; });
        const { descontoCentavos, multaCentavos, jurosCentavos, outrosCentavos, pagoCentavos } = calcularBaixa(valorCentavos, vencimentoIso, datas[i], usados);

        return { valorCentavos, acrescimosCentavos: multaCentavos + jurosCentavos + outrosCentavos, descontoCentavos, pagoCentavos };
    });
    const somar = (campo) => calculadas.reduce((total, linha) => total + linha[campo], 0);

    return {
        linhas: calculadas,
        totais: { valorCentavos: somar('valorCentavos'), acrescimosCentavos: somar('acrescimosCentavos'), descontoCentavos: somar('descontoCentavos'), pagoCentavos: somar('pagoCentavos') },
    };
}

/**
 * Lógica dos campos da BaixaInline e da CorrecaoPagamentoInline sobre um `useForm` com `pago_em`, os sete campos da baixa
 * (`multa_tipo`, `multa`, `juros_tipo`, `juros`, `outros`, `desconto_tipo`, `desconto`) e `errors`. A baixa abre com
 * `paraOForm(camposDaRegra(...))`; trocar a data recalcula só os campos que ainda são os da regra e `recalcular` devolve todos
 * à regra. A correção passa `regras: () => null` (corrige-se o que aconteceu, não há regra a seguir: trocar a data não mexe
 * nos campos) e `jurosGravadoCentavos`: o juros gravado não volta ao campo, que é "ao mês", então com o campo em branco o
 * servidor o mantém (a tela não o envia, ver `pedidoDaCorrecao`) e a conta o usa enquanto houver atraso.
 * `valorCentavos`, `vencimentoIso`, `regras` e `jurosGravadoCentavos` são funções (as props podem mudar).
 */
export function usarValoresDaBaixa(form, { valorCentavos, vencimentoIso, regras, jurosGravadoCentavos = () => 0 }) {
    const daRegra = (dataIso) => camposDaRegra(vencimentoIso(), dataIso, regras());
    const doDia = computed(() => daRegra(form.pago_em));
    const baixa = computed(() => {
        const conta = calcularBaixa(valorCentavos(), vencimentoIso(), form.pago_em, camposDoForm(form));
        const gravado = jurosGravadoCentavos();

        return gravado > 0 && conta.dias > 0 && emBranco(form.juros)
            ? { ...conta, jurosCentavos: gravado, pagoCentavos: conta.pagoCentavos + gravado }
            : conta;
    });
    const temRegra = computed(() => regras() != null);
    const diferenteDaRegra = computed(() => temRegra.value && visiveis(baixa.value.dias).some((nome) => !igual(doForm(form)[nome], doDia.value[nome])));
    const descontoIntegral = computed(() => valorCentavos() > 0 && baixa.value.pagoCentavos === 0);
    const anuncio = computed(() => (descontoIntegral.value ? TEXTO_INTEGRAL : ''));
    const calculados = computed(() => ({
        desconto: baixa.value.descontoCentavos,
        multa: baixa.value.multaCentavos,
        juros: baixa.value.jurosCentavos,
        outros: baixa.value.outrosCentavos,
    }));

    watch(() => form.pago_em, (nova, anterior) => {
        if (temRegra.value) {
            Object.assign(form, paraOForm(camposAoTrocarAData(doForm(form), daRegra(anterior), daRegra(nova))));
        }
    });

    return {
        dias: computed(() => baixa.value.dias),
        valorPagoCentavos: computed(() => baixa.value.pagoCentavos),
        calculados,
        descontoIntegral,
        anuncio,
        temRegra,
        diferenteDaRegra,
        recalcular: () => Object.assign(form, paraOForm(doDia.value)),
        // "contrato" enquanto o campo é o que a regra preencheu; ao mexer, a etiqueta sai.
        etiqueta: (nome) => (doDia.value[nome].numero !== '' && igual(doForm(form)[nome], doDia.value[nome]) ? 'contrato' : ''),
        erroDo: (nome) => form.errors[nome] || form.errors[`${nome}_tipo`],
        limparErro: (nome) => form.clearErrors(nome, `${nome}_tipo`),
    };
}

/**
 * O pedido da correção do pagamento: o formulário inteiro, menos os juros quando o campo está em branco. Os juros digitados
 * são "ao mês" (o servidor os multiplica pelos dias), então o gravado não volta ao campo; sem o campo no pedido o servidor
 * mantém o gravado, e em branco valeria "sem juros". Para tirar os juros, digita-se 0.
 */
export function pedidoDaCorrecao(dados) {
    if (!emBranco(dados.juros)) {
        return dados;
    }
    const { juros: _juros, juros_tipo: _jurosTipo, ...pedido } = dados;

    return pedido;
}
