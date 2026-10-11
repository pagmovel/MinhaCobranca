/** Os relatórios do menu: título e descrição (tela de lista e cabeçalho de cada relatório, antes de os dados chegarem). */
export const RELATORIOS = [
    { tipo: 'faturamento', titulo: 'Faturamento por período', descricao: 'Mês a mês: previsto, recebido e o que ficou em atraso.' },
    { tipo: 'clientes', titulo: 'Receita por cliente', descricao: 'Quanto cada cliente pagou, contratos ativos, valor médio e atrasos.' },
    { tipo: 'produtos', titulo: 'Receita por produto ou serviço', descricao: 'O que mais fatura, pelos itens das cobranças.' },
    { tipo: 'a-receber', titulo: 'Contas a receber', descricao: 'Pendentes por cliente: vencidas e o que vence em 30, 60 e 90 dias.' },
];

export const relatorio = (tipo) => RELATORIOS.find((item) => item.tipo === tipo);
