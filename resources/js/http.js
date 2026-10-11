const FALHA = 'Não foi possível salvar agora. Tente de novo.';
const FALHA_AO_CARREGAR = 'Não foi possível carregar agora. Tente de novo.';

/** Token CSRF que o Laravel deixa no cookie XSRF-TOKEN (o Inertia faz o mesmo nas visitas). */
function tokenCsrf() {
    const par = String(globalThis.document?.cookie ?? '').split('; ').find((item) => item.startsWith('XSRF-TOKEN='));

    return par ? decodeURIComponent(par.slice('XSRF-TOKEN='.length)) : '';
}

/** Pedido JSON que nunca lança: { dados }, { erros } (422, um texto por campo) ou { mensagem }. */
async function pedirJson(url, metodo, corpo, falha) {
    try {
        const resposta = await fetch(url, {
            method: metodo,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(corpo === undefined ? {} : { 'Content-Type': 'application/json' }),
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            body: corpo,
        });
        const dados = await resposta.json().catch(() => null);

        // 200 sem JSON = redirecionamento seguido pelo fetch (ex.: sessão caiu → /entrar): não deu certo.
        if (resposta.ok && dados !== null) {
            return { dados };
        }
        if (resposta.status === 422 && dados?.errors) {
            return { erros: Object.fromEntries(Object.entries(dados.errors).map(([campo, mensagens]) => [campo, mensagens[0]])) };
        }
        if (resposta.status === 419) {
            return { mensagem: 'A sessão expirou. Recarregue a página e tente de novo.' };
        }
        // Pedido JSON sem sessão: o Laravel responde 401 (não redireciona), e "tentar de novo" falharia sempre.
        if (resposta.status === 401) {
            return { mensagem: 'A sessão expirou. Recarregue a página para entrar de novo.' };
        }

        return { mensagem: falha };
    } catch {
        return { mensagem: falha };
    }
}

/**
 * Envia JSON sem sair da página (cadastros rápidos em modal). Devolve { dados }, { erros } (422, um texto por campo)
 * ou { mensagem }; nunca lança, para o formulário de fundo continuar intacto.
 */
export function enviarJson(url, dados, metodo = 'POST') {
    return pedirJson(url, metodo, JSON.stringify(dados), FALHA);
}

/** Busca JSON (GET) sem sair da página: listas carregadas por ajax. Mesmo retorno de enviarJson. */
export function buscarJson(url) {
    return pedirJson(url, 'GET', undefined, FALHA_AO_CARREGAR);
}
