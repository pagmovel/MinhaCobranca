import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ref } from 'vue';
import { acompanharUsuarioDaNovidade, textoDaNovidade, textoDoLugar } from '../../resources/js/novidadeDosTemas.js';

test('casca_lateral_aponta_para_o_canto_de_baixo_do_menu', () => {
    assert.equal(textoDoLugar('lateral'), 'no canto de baixo do menu');
});

test('casca_superior_aponta_para_o_canto_de_cima_a_direita', () => {
    assert.equal(textoDoLugar('superior'), 'no canto de cima, à direita');
});

test('no_celular_o_botao_fica_no_canto_de_cima_mesmo_na_casca_lateral', () => {
    assert.equal(textoDoLugar('lateral', true), 'no canto de cima, à direita');
});

test('casca_desconhecida_cai_no_texto_da_lateral', () => {
    assert.equal(textoDoLugar('qualquer-coisa'), 'no canto de baixo do menu');
    assert.equal(textoDoLugar(undefined), 'no canto de baixo do menu');
});

test('frase_do_popup_traz_o_lugar_e_a_quantidade_de_temas', () => {
    assert.equal(
        textoDaNovidade('lateral', 3),
        'Clique no seu nome, no canto de baixo do menu, e escolha entre 3 temas. Também dá para alterar a senha ali.',
    );
    assert.equal(
        textoDaNovidade('superior', 6),
        'Clique no seu nome, no canto de cima, à direita, e escolha entre 6 temas. Também dá para alterar a senha ali.',
    );
});

test('outro_usuario_na_mesma_aba_nao_herda_a_dispensa_do_anterior', () => {
    const dispensada = ref(false);
    const ultimo = ref(null);
    const visita = (id) => acompanharUsuarioDaNovidade(id, { dispensada, ultimo });

    visita(1);
    dispensada.value = true; // o usuário 1 fechou o popup
    visita(1); // outra tela do mesmo usuário: continua dispensada
    assert.equal(dispensada.value, true);

    visita(null); // sair
    assert.equal(dispensada.value, false);
    dispensada.value = true;
    visita(2); // entrar com outro usuário
    assert.equal(dispensada.value, false);
});
