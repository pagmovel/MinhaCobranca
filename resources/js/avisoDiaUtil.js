import { adiar } from './listaPaginada.js';
import { anoCompleto } from './filtroPeriodo.js';

/**
 * Aviso de vencimento em dia não útil (Fase 23). `conferir(data)` apaga o aviso na hora — o "Usar…" da data anterior não
 * pode continuar clicável durante a espera — e, depois de `ms` sem mudança, pergunta ao servidor (`buscar(data)`, no
 * formato de `buscarJson`). Resposta de uma data já trocada é descartada; dia útil, falha, ano incompleto e campo vazio
 * não mostram aviso.
 */
export function criarAvisoDeDiaUtil(buscar, mostrar, ms = 400) {
    let ultimoPedido = 0;
    let dataAtual = '';

    const perguntar = adiar(async () => {
        const pedido = ultimoPedido;
        const data = dataAtual;
        if (!data || !anoCompleto(data)) {
            return;
        }
        const { dados } = await buscar(data);
        if (pedido === ultimoPedido) {
            mostrar(dados && !dados.util ? dados : null);
        }
    }, ms);

    return (data) => {
        ultimoPedido++;
        dataAtual = data;
        mostrar(null);
        perguntar();
    };
}
