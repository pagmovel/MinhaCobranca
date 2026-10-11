const FALHA = 'Não foi possível consultar agora; preencha manualmente.';

/** Copia para o formulário só o que veio preenchido e só para campos vazios. Devolve quantos campos preencheu. */
export function preencherVazios(form, dados, mapa) {
    let preenchidos = 0;

    for (const [origem, destino] of Object.entries(mapa)) {
        const valor = String(dados?.[origem] ?? '').trim();
        if (valor !== '' && String(form[destino] ?? '').trim() === '') {
            form[destino] = valor;
            preenchidos++;
        }
    }

    return preenchidos;
}

/** GET com a sessão do usuário. Devolve { dados } ou { mensagem }; nunca lança (o formulário segue manual). */
export async function consultar(url) {
    try {
        const resposta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const corpo = await resposta.json().catch(() => ({}));

        return resposta.ok ? { dados: corpo } : { mensagem: corpo.mensagem ?? FALHA };
    } catch {
        return { mensagem: FALHA };
    }
}
