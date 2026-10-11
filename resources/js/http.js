const FALHA = 'Não foi possível salvar agora. Tente de novo.';

/** Token CSRF que o Laravel deixa no cookie XSRF-TOKEN (o Inertia faz o mesmo nas visitas). */
function tokenCsrf() {
    const par = String(globalThis.document?.cookie ?? '').split('; ').find((item) => item.startsWith('XSRF-TOKEN='));

    return par ? decodeURIComponent(par.slice('XSRF-TOKEN='.length)) : '';
}

/**
 * Envia JSON sem sair da página (cadastros rápidos em modal). Devolve { dados }, { erros } (422, um texto por campo)
 * ou { mensagem }; nunca lança, para o formulário de fundo continuar intacto.
 */
export async function enviarJson(url, dados, metodo = 'POST') {
    try {
        const resposta = await fetch(url, {
            method: metodo,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            body: JSON.stringify(dados),
        });
        const corpo = await resposta.json().catch(() => null);

        if (resposta.ok) {
            return { dados: corpo };
        }
        if (resposta.status === 422 && corpo?.errors) {
            return { erros: Object.fromEntries(Object.entries(corpo.errors).map(([campo, mensagens]) => [campo, mensagens[0]])) };
        }
        if (resposta.status === 419) {
            return { mensagem: 'A sessão expirou. Recarregue a página e tente de novo.' };
        }

        return { mensagem: FALHA };
    } catch {
        return { mensagem: FALHA };
    }
}
