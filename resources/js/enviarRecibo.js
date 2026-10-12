import { router } from '@inertiajs/vue3';

function baixar(arquivo) {
    const url = URL.createObjectURL(arquivo);
    Object.assign(document.createElement('a'), { href: url, download: arquivo.name }).click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * Busca o PDF, compartilha (celular + HTTPS) ou baixa, e marca a cobrança como enviada — usado pelo botão
 * individual (`BotaoEnviar.vue`) e pelo envio em lote (T17.19), que só repete isto várias vezes.
 * `AbortError` (fechou a folha sem enviar) não marca como enviada e não é um erro: devolve `false`.
 *
 * @return {Promise<boolean>} true se marcou como enviada
 */
export async function enviarRecibo({ cobrancaId, pdfUrl, nomeArquivo }) {
    const resposta = await fetch(pdfUrl, { credentials: 'same-origin' });
    if (!resposta.ok || !(resposta.headers.get('content-type') ?? '').includes('pdf')) {
        throw new Error('PDF indisponível');
    }
    const arquivo = new File([await resposta.blob()], nomeArquivo, { type: 'application/pdf' });

    if (navigator.canShare?.({ files: [arquivo] })) {
        try {
            await navigator.share({ files: [arquivo], title: nomeArquivo });
        } catch (falha) {
            if (falha.name === 'AbortError') {
                return false;
            }
            if (falha.name !== 'NotAllowedError') {
                throw falha;
            }
            baixar(arquivo); // o navegador exigiu um toque novo depois do download do PDF
        }
    } else {
        baixar(arquivo);
    }

    await new Promise((resolve, reject) => {
        router.post(`/cobrancas/${cobrancaId}/enviada`, {}, { preserveScroll: true, onSuccess: resolve, onError: reject });
    });

    return true;
}
