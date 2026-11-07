import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    MENSAGEM_DE_FALHA,
    MENSAGEM_DE_LIMITE,
    mensagemDaFalhaHttp,
    primeiroCampoComErro,
    validarCamposDeSenha,
} from '../../resources/js/senha.js';

const valida = { senha_atual: 'password', nova_senha: 'NovaSenha#2026', nova_senha_confirmation: 'NovaSenha#2026' };

test('campos_corretos_nao_tem_erro', () => {
    assert.deepEqual(validarCamposDeSenha(valida), {});
});

test('campos_vazios_pedem_os_tres', () => {
    assert.deepEqual(validarCamposDeSenha({ senha_atual: '', nova_senha: '', nova_senha_confirmation: '' }), {
        senha_atual: 'Digite a senha atual.',
        nova_senha: 'Digite a nova senha.',
        nova_senha_confirmation: 'Repita a nova senha.',
    });
    assert.deepEqual(Object.keys(validarCamposDeSenha({})), ['senha_atual', 'nova_senha', 'nova_senha_confirmation']);
});

test('confirmacao_diferente_aponta_para_o_campo_da_confirmacao', () => {
    const erros = validarCamposDeSenha({ ...valida, nova_senha_confirmation: 'NovaSenha#2027' });

    assert.deepEqual(erros, { nova_senha_confirmation: 'A confirmação não confere com a nova senha.' });
});

test('menos_de_8_caracteres_e_recusada', () => {
    const erros = validarCamposDeSenha({ ...valida, nova_senha: 'Curta1!', nova_senha_confirmation: 'Curta1!' });

    assert.equal(erros.nova_senha, 'A nova senha precisa ter pelo menos 8 caracteres.');
    assert.equal(erros.nova_senha_confirmation, undefined);
});

test('exatamente_8_caracteres_passa_e_conta_letra_acentuada_como_uma', () => {
    assert.deepEqual(validarCamposDeSenha({ ...valida, nova_senha: 'açãoção1', nova_senha_confirmation: 'açãoção1' }), {});
    assert.equal(validarCamposDeSenha({ ...valida, nova_senha: 'açãoçã', nova_senha_confirmation: 'açãoçã' }).nova_senha?.includes('pelo menos 8'), true);
});

test('igual_a_atual_e_recusada', () => {
    const erros = validarCamposDeSenha({ senha_atual: 'SenhaAntiga1', nova_senha: 'SenhaAntiga1', nova_senha_confirmation: 'SenhaAntiga1' });

    assert.deepEqual(erros, { nova_senha: 'A nova senha precisa ser diferente da atual.' });
});

test('espaco_nas_pontas_da_nova_senha_vale_porque_o_servidor_nao_apara', () => {
    // O servidor guarda a senha como veio (`trimStrings(except: ...)`, AlterarSenhaTest) e o login também não apara.
    for (const nova of [' NovaSenha#2026', 'NovaSenha#2026 ', 'Nova Senha 2026']) {
        assert.deepEqual(validarCamposDeSenha({ ...valida, nova_senha: nova, nova_senha_confirmation: nova }), {}, nova);
    }
    // O espaço conta no tamanho mínimo, como no servidor: sete letras e um espaço dão oito caracteres.
    assert.deepEqual(validarCamposDeSenha({ ...valida, nova_senha: 'Senha12 ', nova_senha_confirmation: 'Senha12 ' }), {});
});

test('primeiro_campo_com_erro_segue_a_ordem_da_tela', () => {
    assert.equal(primeiroCampoComErro({ nova_senha_confirmation: 'x', nova_senha: 'y' }), 'nova_senha');
    assert.equal(primeiroCampoComErro({ senha_atual: 'z', nova_senha: 'y' }), 'senha_atual');
    assert.equal(primeiroCampoComErro({}), null);
    assert.equal(primeiroCampoComErro(undefined), null);
    // Erro limpo ao digitar fica `undefined` na chave: não conta.
    assert.equal(primeiroCampoComErro({ senha_atual: undefined, nova_senha: 'y' }), 'nova_senha');
});

test('429_mostra_o_aviso_de_limite_e_o_resto_a_falha_generica', () => {
    assert.equal(mensagemDaFalhaHttp(429), MENSAGEM_DE_LIMITE);
    assert.equal(MENSAGEM_DE_LIMITE, 'Muitas tentativas. Aguarde um minuto e tente de novo.');
    assert.equal(mensagemDaFalhaHttp(500), MENSAGEM_DE_FALHA);
    assert.equal(mensagemDaFalhaHttp(undefined), MENSAGEM_DE_FALHA);
});
