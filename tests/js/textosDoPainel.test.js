import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { textosDoPainel, vazioDeEnviar } from '../../resources/js/textosDoPainel.js';

test('lista_vazia_diz_quantos_dias_antes_do_vencimento_e_concorda_no_singular', () => {
    assert.match(vazioDeEnviar(10), /10 dias antes do vencimento/);
    assert.match(vazioDeEnviar(1), /1 dia antes do vencimento/);
});

test('etapas_tem_titulo_aba_e_dica_para_a_pessoa_entender_o_que_fazer', () => {
    for (const etapa of [textosDoPainel.enviar, textosDoPainel.aguardando]) {
        assert.ok(etapa.titulo && etapa.aba && etapa.dica);
    }
    assert.match(textosDoPainel.enviar.titulo, /recibo/i);
});

test('nenhuma_tela_do_painel_volta_a_escrever_para_enviar_a_mao', () => {
    for (const pasta of ['Components/Painel/', 'Components/']) {
        const raiz = new URL(`../../resources/js/${pasta}`, import.meta.url);
        for (const nome of readdirSync(raiz).filter((arquivo) => arquivo.endsWith('.vue'))) {
            assert.doesNotMatch(readFileSync(new URL(nome, raiz), 'utf8'), /Para enviar/, `${pasta}${nome}`);
        }
    }
});
