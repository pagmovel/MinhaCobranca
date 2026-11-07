/** Textos do formulário de troca de senha. As mensagens de validação repetem as do servidor (`AlterarSenhaRequest`). */
export const MENSAGEM_DE_LIMITE = 'Muitas tentativas. Aguarde um minuto e tente de novo.';
export const MENSAGEM_DE_FALHA = 'Não foi possível alterar a senha agora. Tente de novo.';

export const MINIMO_DA_SENHA = 8;

/** Ordem em que os campos aparecem na tela: o foco vai ao primeiro com erro. */
export const CAMPOS_DA_SENHA = ['senha_atual', 'nova_senha', 'nova_senha_confirmation'];

/**
 * Confere o que dá para conferir sem o servidor, para o erro aparecer antes de gastar uma das 6 tentativas por minuto.
 * Devolve um texto por campo com problema (objeto vazio = nada a corrigir). O servidor continua sendo a fonte da
 * verdade: ele decide se a senha atual está certa e repete estas regras.
 */
export function validarCamposDeSenha({ senha_atual = '', nova_senha = '', nova_senha_confirmation = '' }) {
    const erros = {};

    if (senha_atual === '') {
        erros.senha_atual = 'Digite a senha atual.';
    }

    if (nova_senha === '') {
        erros.nova_senha = 'Digite a nova senha.';
    } else if ([...nova_senha].length < MINIMO_DA_SENHA) {
        erros.nova_senha = `A nova senha precisa ter pelo menos ${MINIMO_DA_SENHA} caracteres.`;
    } else if (nova_senha === senha_atual) {
        erros.nova_senha = 'A nova senha precisa ser diferente da atual.';
    }

    if (nova_senha_confirmation === '') {
        erros.nova_senha_confirmation = 'Repita a nova senha.';
    } else if (nova_senha_confirmation !== nova_senha) {
        erros.nova_senha_confirmation = 'A confirmação não confere com a nova senha.';
    }

    return erros;
}

/** Primeiro campo, na ordem da tela, que tem erro; `null` se nenhum. */
export function primeiroCampoComErro(erros) {
    return CAMPOS_DA_SENHA.find((campo) => erros?.[campo]) ?? null;
}

/** Texto para uma resposta do servidor que não é erro de validação (o Inertia não a trata). */
export function mensagemDaFalhaHttp(status) {
    return status === 429 ? MENSAGEM_DE_LIMITE : MENSAGEM_DE_FALHA;
}
