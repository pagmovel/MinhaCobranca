import { computed, watch } from 'vue';
import { criarAvisoTemporario } from './avisoTemporario.js';
import { descontoEmCentavos, semSeparadorFinal, travarDesconto } from './desconto.js';
import { formatarCentavos } from './itens.js';

export const TEXTO_INTEGRAL = 'Desconto integral: nada será recebido.';

/**
 * Lógica dos campos de desconto da baixa (BaixaInline e CorrecaoPagamentoInline) sobre um `useForm` com
 * `desconto_tipo` e `desconto`: valor pago ao vivo, trava em 100%, trocar % ↔ R$ limpa o número, aviso que some.
 * `valorCentavos` é uma função (a prop pode mudar; no lote, a menor cobrança). A trava roda num `watch` do número, não
 * num `@input`: a ordem entre `v-model` e `v-mascara` mudaria o texto que o `@input` enxerga.
 * `complementoDoMaximo`: texto depois de "Máximo: R$ X,XX" (no lote, " (valor da menor cobrança)").
 */
export function usarDescontoDaBaixa(form, valorCentavos, { complementoDoMaximo = '' } = {}) {
    const emReais = computed(() => form.desconto_tipo === 'valor');
    // A vírgula no fim ("12,") é digitação em curso: conta como o número antes dela, senão o valor pago pisca.
    const descontoCentavos = computed(() => descontoEmCentavos(valorCentavos(), form.desconto_tipo, semSeparadorFinal(form.desconto)) ?? 0);
    const valorPagoCentavos = computed(() => valorCentavos() - descontoCentavos.value);
    const descontoIntegral = computed(() => valorCentavos() > 0 && descontoCentavos.value === valorCentavos());
    const { aviso, mostrar, limpar } = criarAvisoTemporario();
    const anuncio = computed(() => aviso.value || (descontoIntegral.value ? TEXTO_INTEGRAL : ''));
    const erroDoDesconto = computed(() => form.errors.desconto || form.errors.desconto_tipo);

    // Trocar % ↔ R$ limpa o número: 10% não pode virar R$ 0,10.
    watch(() => form.desconto_tipo, () => {
        limpar();
        form.desconto = '';
    });

    watch(() => form.desconto, () => {
        form.clearErrors('desconto', 'desconto_tipo');
        const { entrada, travou } = travarDesconto(valorCentavos(), form.desconto_tipo, form.desconto);
        if (travou) {
            form.desconto = entrada;
            mostrar(emReais.value ? `Máximo: R$ ${formatarCentavos(valorCentavos())}${complementoDoMaximo}` : 'Máximo: 100%');
        }
    });

    return { emReais, valorPagoCentavos, descontoIntegral, aviso, anuncio, erroDoDesconto };
}
