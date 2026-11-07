import { test } from 'node:test';
import assert from 'node:assert/strict';
import { explicacaoDoBloqueio, iconeDoItem, itensDoMenu } from '../../resources/js/menuPrincipal.js';

const progressoVazio = { perfil: false, catalogo: false, liberado: false, pendencias: [] };
const progressoCompleto = { perfil: true, catalogo: true, liberado: true, pendencias: [] };

test('administrador_ve_usuarios_com_contador', () => {
    const itens = itensDoMenu(progressoCompleto, true, 4);

    assert.deepEqual(itens.at(-1), { href: '/admin/usuarios', rotulo: 'Usuários', contador: 4 });
});

test('usuario_comum_nao_ve_usuarios', () => {
    const itens = itensDoMenu(progressoCompleto, false, 4);

    assert.equal(itens.some((item) => item.href === '/admin/usuarios'), false);
    assert.deepEqual(
        itens.map((item) => item.rotulo),
        ['Painel', 'Cobranças', 'Contratos', 'Clientes', 'Produtos e serviços', 'Relatórios', 'Auditoria', 'Meu perfil'],
    );
});

test('item_bloqueado_segue_o_progresso', () => {
    const bloqueados = (progresso) => itensDoMenu(progresso, false, 0).filter((item) => item.bloqueado).map((item) => item.href);

    assert.deepEqual(bloqueados(progressoVazio), ['/cobrancas', '/contratos', '/clientes', '/produtos-servicos']);
    assert.deepEqual(bloqueados({ ...progressoVazio, perfil: true }), ['/cobrancas', '/contratos']);
    assert.deepEqual(bloqueados(progressoCompleto), []);
    assert.deepEqual(bloqueados(null), []);
});

test('nao_inclui_sair', () => {
    for (const administrador of [false, true]) {
        const itens = itensDoMenu(progressoCompleto, administrador, 0);

        assert.equal(itens.some((item) => /sair/i.test(item.rotulo) || item.href === '/sair'), false);
    }
});

test('explicacao_do_bloqueio_depende_do_item', () => {
    assert.match(explicacaoDoBloqueio('/cobrancas'), /produto ou serviço e um cliente/);
    assert.equal(explicacaoDoBloqueio('/contratos'), explicacaoDoBloqueio('/cobrancas'));
    assert.match(explicacaoDoBloqueio('/clientes'), /Complete seu perfil/);
    assert.equal(explicacaoDoBloqueio('/produtos-servicos'), explicacaoDoBloqueio('/clientes'));
});

test('todo_item_do_menu_tem_icone', () => {
    const progresso = { perfil: true, catalogo: true, liberado: true, pendencias: [] };
    for (const item of itensDoMenu(progresso, true, 1)) {
        assert.match(iconeDoItem(item.href), /^M[\d.\-\sMLHVCSQTAZmlhvcsqtaz]+$/, `${item.href} sem ícone`);
    }
    assert.equal(iconeDoItem('/nao-existe'), '');
});
