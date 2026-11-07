import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    NIVEIS_POR_DIA, acumuladoPorDia, caminhosDaRegua, colunasDoMes, descricaoDoPonto, diaDaSemana, diaDoPonto, empilharPontos, frasePeloDia,
    linhasDaGrade, ordemDeTabulacao, posicaoDoDia, previstoPorDia, somasPorEtapa, tipoDoPonto, valorCompacto, vizinhoDoPonto,
} from '../../resources/js/linhaDoTempo.js';

const ponto = (id, etapa, vencimento_dia, pago_dia = null, pago_centavos = null) => ({
    id,
    cliente: `Cliente ${id}`,
    valor_centavos: 10000,
    vencimento_dia,
    etapa,
    situacao: etapa === 'recebida' ? 'paga' : 'a_vencer',
    pago_dia,
    pago_centavos,
});

const quase = (obtido, esperado) => assert.ok(Math.abs(obtido - esperado) < 1e-9, `${obtido} != ${esperado}`);

test('dois_no_mesmo_dia_empilham', () => {
    // Entram fora de ordem de id: o nível segue o id crescente, a lista mantém a ordem de entrada.
    const pontos = [ponto(7, 'aguardando', 10), ponto(3, 'enviar', 10), ponto(5, 'aguardando', 11), ponto(9, 'aguardando', 10)];

    assert.deepEqual(
        empilharPontos(pontos).map(({ ponto: p, nivel }) => [p.id, nivel]),
        [[7, 1], [3, 0], [5, 0], [9, 2]],
    );
});

test('recebida_empilha_no_dia_do_pagamento', () => {
    const pontos = [ponto(1, 'aguardando', 20), ponto(2, 'recebida', 5, 20, 10000), ponto(3, 'recebida', 20, 5, 10000)];

    assert.equal(diaDoPonto(pontos[1]), 20);
    assert.equal(diaDoPonto(pontos[2]), 5);
    assert.deepEqual(empilharPontos(pontos).map(({ nivel }) => nivel), [0, 1, 0]);
});

test('acumulado_so_soma_recebidos', () => {
    const pontos = [
        ponto(1, 'recebida', 3, 2, 10000),
        ponto(2, 'recebida', 3, 2, 5000),
        ponto(3, 'recebida', 9, 4, 20000),
        ponto(4, 'aguardando', 4),
        ponto(5, 'enviar', 1),
    ];
    const acumulado = acumuladoPorDia(pontos, 5);

    assert.deepEqual(acumulado, [0, 15000, 15000, 35000, 35000]);
});

test('mes_de_28_dias', () => {
    assert.equal(acumuladoPorDia([ponto(1, 'recebida', 28, 28, 100)], 28).length, 28);
    assert.equal(acumuladoPorDia([ponto(1, 'recebida', 28, 28, 100)], 28).at(-1), 100);
    quase(posicaoDoDia(28, 28), 27.5 / 28);
});

test('mes_de_31_dias', () => {
    const acumulado = acumuladoPorDia([ponto(1, 'recebida', 31, 31, 100), ponto(2, 'recebida', 1, 1, 50)], 31);

    assert.equal(acumulado.length, 31);
    assert.equal(acumulado[0], 50);
    assert.equal(acumulado.at(-1), 150);
    quase(posicaoDoDia(31, 31), 30.5 / 31);
});

test('sem_pontos_devolve_zeros', () => {
    assert.deepEqual(acumuladoPorDia([], 4), [0, 0, 0, 0]);
    assert.deepEqual(empilharPontos([]), []);
});

test('posicao_do_primeiro_e_do_ultimo_dia', () => {
    quase(posicaoDoDia(1, 30), 0.5 / 30);
    quase(posicaoDoDia(30, 30), 29.5 / 30);
});

// Novembro de 2026 começa num domingo; hoje é dia 6 (sexta); Finados (2) é feriado.
const MES = { mes: '2026-11', hoje: 6, dias: 30, feriados: { 2: 'Finados' }, fim_de_semana: [1, 7, 8] };
const comSituacao = (p, situacao) => ({ ...p, situacao });

test('dia_da_semana_nao_depende_do_fuso', () => {
    assert.equal(diaDaSemana('2026-11', 1), 0);
    assert.equal(diaDaSemana('2026-11', 6), 5);
    assert.equal(diaDaSemana('2026-02', 28), 6);
});

test('valor_compacto_cabe_na_pilula', () => {
    assert.equal(valorCompacto(65000), '650');
    assert.equal(valorCompacto(350000), '3500');
    assert.equal(valorCompacto(1234500), '12k');
    assert.equal(valorCompacto(99960000), '1,0M'); // 999,6 mil arredonda para 1000k: vira milhão
    assert.equal(valorCompacto(250000000), '2,5M');
    assert.equal(valorCompacto(0), '0');
});

test('tipo_do_ponto_situacao_vale_mais_que_etapa', () => {
    assert.equal(tipoDoPonto(comSituacao(ponto(1, 'aguardando', 3), 'vencida')), 'vencida');
    assert.equal(tipoDoPonto(comSituacao(ponto(1, 'enviar', 6), 'vence_hoje')), 'hoje');
    assert.equal(tipoDoPonto(comSituacao(ponto(1, 'enviar', 9), 'a_vencer')), 'enviar');
    assert.equal(tipoDoPonto(comSituacao(ponto(1, 'aguardando', 9), 'a_vencer')), 'aguardando');
    assert.equal(tipoDoPonto({ ...ponto(1, 'recebida', 3, 5, 100), situacao: 'vencida' }), 'recebida');
});

test('colunas_separam_vencimentos_e_pagamentos_por_dia', () => {
    const pontos = [ponto(7, 'aguardando', 10), ponto(3, 'enviar', 10), ponto(5, 'recebida', 2, 10, 100), ponto(9, 'aguardando', 11)];
    const colunas = colunasDoMes(pontos, 30);

    assert.equal(colunas.length, 30);
    assert.deepEqual(colunas[9].vencimentos.map(({ ponto: p, nivel }) => [p.id, nivel]), [[3, 0], [7, 1]]);
    assert.deepEqual(colunas[9].pagamentos.map(({ ponto: p, nivel }) => [p.id, nivel]), [[5, 0]]);
    assert.equal(colunas[1].pagamentos.length + colunas[1].vencimentos.length, 0);
    assert.deepEqual(ordemDeTabulacao(colunas), [3, 7, 5, 9]);
});

test('pilha_ate_o_teto_nao_tem_pilula_e_acima_dele_o_ultimo_nivel_vira_o_mais_n', () => {
    const noDia = (quantidade, etapa = 'aguardando') => Array.from({ length: quantidade }, (_, i) => ponto(i + 1, etapa, 10));

    const noTeto = colunasDoMes(noDia(NIVEIS_POR_DIA), 30)[9];
    assert.equal(noTeto.vencimentos.length, NIVEIS_POR_DIA);
    assert.equal(noTeto.maisVencimentos, 0);

    const acima = colunasDoMes(noDia(NIVEIS_POR_DIA + 1), 30)[9];
    assert.equal(acima.vencimentos.length, NIVEIS_POR_DIA - 1);
    assert.equal(acima.maisVencimentos, 2); // o "+N" ocupa o último nível: nunca esconde um ponto só
    assert.deepEqual(acima.vencimentos.map(({ ponto: p }) => p.id), [1, 2, 3, 4, 5]);
});

test('quarenta_no_mesmo_dia_ficam_em_seis_niveis_nos_dois_lados_e_a_ordem_do_tab_so_tem_os_visiveis', () => {
    const pontos = [
        ...Array.from({ length: 40 }, (_, i) => ponto(i + 1, 'aguardando', 10)),
        ...Array.from({ length: 40 }, (_, i) => ponto(100 + i, 'recebida', 3, 10, 5000)),
    ];
    const coluna = colunasDoMes(pontos, 30)[9];

    assert.equal(coluna.vencimentos.length + (coluna.maisVencimentos ? 1 : 0), NIVEIS_POR_DIA);
    assert.equal(coluna.pagamentos.length + (coluna.maisPagamentos ? 1 : 0), NIVEIS_POR_DIA);
    assert.equal(coluna.maisVencimentos, 35);
    assert.equal(coluna.maisPagamentos, 35);
    assert.ok(coluna.vencimentos.every(({ nivel }) => nivel < NIVEIS_POR_DIA));
    assert.equal(ordemDeTabulacao(colunasDoMes(pontos, 30)).length, 10);
});

test('os_pontos_escondidos_continuam_na_regua_de_dinheiro', () => {
    const pontos = Array.from({ length: 40 }, (_, i) => ponto(i + 1, 'recebida', 3, 10, 5000));

    assert.equal(colunasDoMes(pontos, 30)[9].pagamentos.length, NIVEIS_POR_DIA - 1);
    assert.equal(acumuladoPorDia(pontos, 30)[29], 40 * 5000);
    assert.equal(somasPorEtapa(pontos).recebido, 40 * 5000);
});

test('vizinho_do_ponto_para_nas_pontas', () => {
    const ordem = [4, 8, 15];

    assert.equal(vizinhoDoPonto(ordem, 8, 1), 15);
    assert.equal(vizinhoDoPonto(ordem, 8, -1), 4);
    assert.equal(vizinhoDoPonto(ordem, 15, 1), 15);
    assert.equal(vizinhoDoPonto(ordem, 4, -1), 4);
    assert.equal(vizinhoDoPonto(ordem, 99, 1), 4);
});

test('previsto_junta_o_recebido_e_o_que_falta_no_vencimento_ou_hoje', () => {
    const pontos = [
        ponto(1, 'recebida', 2, 2, 1000),
        ponto(2, 'aguardando', 4), // atrasada: entra em hoje (dia 5)
        ponto(3, 'enviar', 8),
    ];
    // ponto() dá valor_centavos 10000 a todos.
    assert.deepEqual(previstoPorDia(pontos, 10, 5), [0, 1000, 1000, 1000, 11000, 11000, 11000, 21000, 21000, 21000]);
    assert.deepEqual(previstoPorDia([], 3, 1), [0, 0, 0]);
});

test('somas_por_etapa', () => {
    const pontos = [ponto(1, 'recebida', 2, 2, 9000), ponto(2, 'aguardando', 4), ponto(3, 'enviar', 8), ponto(4, 'enviar', 9)];

    assert.deepEqual(somasPorEtapa(pontos), { recebido: 9000, aguardando: 10000, enviar: 20000, previsto: 39000 });
});

test('previsto_e_o_mesmo_numero_no_total_e_no_ultimo_dia_da_linha', () => {
    // O servidor (Talão) soma recebido + pendentes do mês; a Régua e o Almanaque somam os pontos: tem de dar igual.
    const pontos = [ponto(1, 'recebida', 2, 2, 19000), ponto(2, 'recebida', 3, 4, 5000), ponto(3, 'aguardando', 4), ponto(4, 'enviar', 20)];
    const { recebido, aguardando, enviar, previsto } = somasPorEtapa(pontos);

    assert.equal(previsto, recebido + aguardando + enviar);
    assert.equal(previsto, 24000 + 20000);
    assert.equal(previstoPorDia(pontos, 30, 10).at(-1), previsto);
});

test('regua_comeca_embaixo_e_termina_no_previsto', () => {
    const pontos = [ponto(1, 'recebida', 1, 1, 5000), ponto(2, 'enviar', 3)];
    const recebido = acumuladoPorDia(pontos, 4);
    const previsto = previstoPorDia(pontos, 4, 2);
    const caminhos = caminhosDaRegua({ recebido, previsto, dias: 4, hoje: 2 });

    assert.equal(caminhos.maximo, 15000);
    assert.ok(caminhos.linha.startsWith('M0 100 L'));
    assert.ok(caminhos.area.endsWith(`L${caminhos.larguraRecebido} 100 Z`));
    assert.equal(caminhos.larguraRecebido, 150);
    assert.equal(caminhos.larguraTotal, 400);
    assert.ok(caminhos.projecao.endsWith('L400 0'), caminhos.projecao); // o último ponto do pontilhado é o previsto, no topo
    assert.equal(caminhos.pontaY, 66.67); // 5000 de 15000
});

test('regua_sem_pontos_nao_divide_por_zero', () => {
    const caminhos = caminhosDaRegua({ recebido: [0, 0], previsto: [0, 0], dias: 2, hoje: 1 });

    assert.equal(caminhos.maximo, 1);
    assert.ok(!caminhos.linha.includes('NaN') && !caminhos.projecao.includes('NaN'));
});

test('grade_tem_no_maximo_tres_linhas_redondas_abaixo_do_topo', () => {
    assert.deepEqual(linhasDaGrade(1500000), [500000, 1000000]);
    assert.deepEqual(linhasDaGrade(100000), [50000]); // R$ 1.000 de topo: passo de R$ 500; 1.000 é o topo e fica de fora
    assert.ok(linhasDaGrade(100000).length <= 3);
    assert.deepEqual(linhasDaGrade(1), []);
    assert.ok(linhasDaGrade(9999999999).length <= 3);
});

test('descricao_do_ponto', () => {
    const vencida = comSituacao(ponto(1, 'aguardando', 4), 'vencida');
    const hoje = comSituacao(ponto(2, 'enviar', 6), 'vence_hoje');
    const breve = comSituacao(ponto(3, 'aguardando', 7), 'a_vencer');
    const emFeriado = comSituacao(ponto(4, 'aguardando', 2), 'vencida');
    const nobreFimDeSemana = comSituacao(ponto(5, 'enviar', 8), 'a_vencer');

    assert.equal(descricaoDoPonto(vencida, MES), 'Aguardando pagamento, vencida há 2 dias');
    assert.equal(descricaoDoPonto(hoje, MES), 'Recibo ainda não enviado, vence hoje');
    assert.equal(descricaoDoPonto(breve, MES), 'Aguardando pagamento, vence em 1 dia, sábado, 7/11. Cai no fim de semana');
    assert.equal(descricaoDoPonto(emFeriado, MES), 'Aguardando pagamento, vencida há 4 dias. Cai em feriado: Finados');
    assert.equal(descricaoDoPonto(nobreFimDeSemana, MES), 'Recibo ainda não enviado, vence em 2 dias, domingo, 8/11. Cai no fim de semana');
    assert.equal(descricaoDoPonto(ponto(6, 'recebida', 3, 5, 100), MES), 'Recebida em 5/11');
});

test('frase_pelo_dia', () => {
    assert.equal(frasePeloDia({ mes: '2026-11', hoje: 6, dias: 30 }), 'Sexta, 6 de novembro. Faltam 24 dias para fechar o mês.');
    assert.equal(frasePeloDia({ mes: '2026-11', hoje: 29, dias: 30 }), 'Domingo, 29 de novembro. Falta 1 dia para fechar o mês.');
    assert.equal(frasePeloDia({ mes: '2026-11', hoje: 30, dias: 30 }), 'Segunda, 30 de novembro. Último dia do mês.');
});
