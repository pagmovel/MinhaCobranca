<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo {{ $numero }}</title>
<style>
    @page { margin: 12pt; }
    body { margin: 0; font-family: Helvetica, sans-serif; font-size: 6.5pt; color: #000; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { vertical-align: top; padding: 1pt 3pt; overflow: hidden; }
    .c1 { width: 25%; } .c2 { width: 50%; } .c3 { width: 75%; } .c4 { width: 100%; }
    .rot { display: block; font-size: 6pt; font-weight: bold; }
    .val { display: block; min-height: 7pt; font-size: 6.5pt; }
    .bloco { border-top: 0.75pt solid #000; }
    .titulo { background: #E6E6E6; font-size: 7pt; font-weight: bold; }
    .topo { position: relative; }
    .carimbo { position: absolute; border: 2.5pt solid; padding: 3pt 8pt; font-weight: bold; text-align: center; }
    .carimbo-detalhe { font-size: 7pt; }
    .carimbo-pago { color: #2E7D4F; border-color: #2E7D4F; }
    .carimbo-a_vencer { color: #2F3C9E; border-color: #2F3C9E; }
    .carimbo-vencida { color: #B3261E; border-color: #B3261E; }
    .carimbo-vence_hoje { color: #C2410C; border-color: #C2410C; }
    .carimbo-cancelada { color: #6B7280; border-color: #6B7280; }
    .rodape { position: fixed; bottom: 0; left: 0; right: 0; }
    .rodape td { height: 30pt; border: 0.75pt solid #000; }
</style>
</head>
<body>
<div class="topo">
    <table>
        <tr>
            <td style="width: 30%; height: 40pt;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="max-width: 150pt; max-height: 38pt;">
                @else
                    <span style="font-size: 12pt; font-weight: bold;">{{ $prestador['nome'] }}</span>
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <div style="font-size: 9pt; font-weight: bold;">RECIBO</div>
                <div style="font-size: 8pt; font-weight: bold;">Recibo de Prestação de Serviços</div>
            </td>
            <td style="width: 30%;"><span class="val">Município: {{ $prestador['municipio'] }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="c4"><span class="rot">NÚMERO DO RECIBO</span><span class="val" style="font-size: 9pt; font-weight: bold;">{{ $numero }}</span></td></tr></table>
    <table><tr>
        <td class="c1"><span class="rot">ANO / SEQUÊNCIA</span><span class="val">{{ $ano_sequencia }}</span></td>
        <td class="c1"><span class="rot">COMPETÊNCIA</span><span class="val">{{ $competencia }}</span></td>
        <td class="c1"><span class="rot">DATA E HORA DA EMISSÃO</span><span class="val">{{ $emitido_em }}</span></td>
        <td class="c1"></td>
    </tr></table>
    <table><tr>
        <td class="c1"><span class="rot">VENCIMENTO</span><span class="val">{{ $vencimento }}</span></td>
        <td class="c1"><span class="rot">PERÍODO</span><span class="val">{{ $servico['periodo'] }}</span></td>
        <td class="c1"><span class="rot">DATA DO PAGAMENTO</span><span class="val">{{ $pago_em }}</span></td>
        <td class="c1"></td>
    </tr></table>
    <table><tr>
        <td class="c1"><span class="rot">EMITENTE DO RECIBO</span><span class="val">Prestador</span></td>
        <td class="c1"><span class="rot">SITUAÇÃO DO RECIBO</span><span class="val">{{ $situacao }}</span></td>
        <td class="c1"><span class="rot">FINALIDADE</span><span class="val">-</span></td>
        <td class="c1"></td>
    </tr></table>

    {{-- No original, o QR code da NFS-e ocupa este canto. --}}
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 52pt; right: 14pt; width: 110pt; font-size: '.($carimbo['classe'] === 'vence_hoje' ? '10' : '14').'pt; transform: rotate(-8deg);'])
</div>

@foreach (['PRESTADOR / FORNECEDOR' => $prestador, 'TOMADOR / ADQUIRENTE' => $tomador] as $titulo => $parte)
    <table class="bloco"><tr>
        <td class="c1 titulo">{{ $titulo }}</td>
        <td class="c1"><span class="rot">CNPJ / CPF / NIF</span><span class="val">{{ $parte['documento'] }}</span></td>
        <td class="c1"><span class="rot">Indicador Municipal (Inscrição)</span><span class="val">{{ $parte['inscricao_municipal'] }}</span></td>
        <td class="c1"><span class="rot">Telefone</span><span class="val">{{ $parte['telefone'] }}</span></td>
    </tr></table>
    <table><tr>
        <td class="c2"><span class="rot">Nome / Nome Empresarial</span><span class="val">{{ $parte['nome'] }}</span></td>
        <td class="c1"><span class="rot">Município / Sigla UF</span><span class="val">{{ $parte['municipio'] }}</span></td>
        <td class="c1"><span class="rot">CEP</span><span class="val">{{ $parte['cep'] }}</span></td>
    </tr></table>
    {{-- v2 (T17.13): apelido do cliente = unidade atendida; o tomador continua com nome e CPF/CNPJ oficiais. --}}
    @if (! $loop->first && $parte['apelido'] !== '')
        <table><tr><td class="c4"><span class="rot">Unidade atendida</span><span class="val">{{ $parte['apelido'] }}</span></td></tr></table>
    @endif
    <table><tr>
        <td class="c2"><span class="rot">Endereço</span><span class="val">{{ $parte['endereco'] }}</span></td>
        <td class="c2"><span class="rot">E-mail</span><span class="val">{{ $parte['email'] }}</span></td>
    </tr></table>
    @if ($loop->first)
        <table><tr>
            <td class="c1"><span class="rot">Simples Nacional na Data de Competência</span><span class="val">-</span></td>
            <td class="c3"><span class="rot">Regime de Apuração Tributária pelo SN</span><span class="val">-</span></td>
        </tr></table>
    @endif
@endforeach

<table class="bloco"><tr>
    <td class="c1 titulo">SERVIÇO PRESTADO</td>
    <td class="c1"><span class="rot">Código de Tributação Nacional/Municipal</span><span class="val">{{ $servico['codigo_tributacao_nacional'] }}</span></td>
    <td class="c1"><span class="rot">Código da NBS</span><span class="val">{{ $servico['codigo_nbs'] }}</span></td>
    <td class="c1"><span class="rot">Local da Prestação / Sigla UF / País</span><span class="val">{{ $prestador['municipio'] }} / -</span></td>
</tr></table>
<table><tr><td class="c4"><span class="val">{{ $servico['codigo_servico'] }}</span></td></tr></table>
<table><tr><td class="c4"><span class="rot">Descrição do Serviço</span><span class="val">{{ $servico['descricao_em_linha'] }} {{ $servico['obs_periodo'] }}</span></td></tr></table>

@foreach ([
    ['TRIBUTAÇÃO MUNICIPAL (ISSQN)', ['Tipo de Tributação do ISSQN', 'Município / Sigla UF / País de Incidência do ISSQN', ''], [
        ['BC ISSQN', 'Alíquota Aplicada', 'Retenção do ISSQN', 'ISSQN Apurado'],
    ]],
    ['TRIBUTAÇÃO FEDERAL (EXCETO CBS)', ['IRRF', 'Contribuição Previdenciária - Retida', 'Contribuições Sociais - Retidas'], [
        ['PIS - Débito Apuração Própria', 'COFINS - Débito Apuração Própria', 'Descrição Contrib. Sociais - Retidas', ''],
    ]],
    ['TRIBUTAÇÃO IBS/CBS', ['CST / cClassTrib', 'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF', ''], [
        ['Exclusões e Reduções da Base de Cálculo', 'Base de Cálculo Após Exclusões e Reduções', 'Red. Alíquota IBS / Red. Alíquota CBS', 'Alíquota - IBS UF / IBS Mun'],
        ['Alíq. Efetiva Municipal - IBS', 'Valor Apurado Municipal - IBS', 'Alíq. Efetiva Estadual - IBS', 'Valor Apurado Estadual - IBS'],
        ['Valor Total Apurado - IBS', 'Alíquota - CBS', 'Alíquota Efetiva - CBS', 'Valor Total Apurado - CBS'],
    ]],
] as [$titulo, $primeira, $linhas])
    {{-- Quadros mantidos só no visual: o recibo não calcula tributos. --}}
    <table class="bloco"><tr>
        <td class="c1 titulo">{{ $titulo }}</td>
        @foreach ($primeira as $rotulo)
            <td class="c1">@if ($rotulo !== '')<span class="rot">{{ $rotulo }}</span><span class="val">-</span>@endif</td>
        @endforeach
    </tr></table>
    @foreach ($linhas as $linha)
        <table><tr>
            @foreach ($linha as $rotulo)
                <td class="c1">@if ($rotulo !== '')<span class="rot">{{ $rotulo }}</span><span class="val">-</span>@endif</td>
            @endforeach
        </tr></table>
    @endforeach
@endforeach

<table class="bloco"><tr>
    <td class="c1 titulo">VALOR TOTAL DO RECIBO</td>
    <td class="c1"><span class="rot">VALOR DA OPERAÇÃO / SERVIÇO</span><span class="val">R$ {{ $valor }}</span></td>
    <td class="c1"><span class="rot">Desconto Incondicionado</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">Desconto Condicionado</span><span class="val">-</span></td>
</tr></table>
<table><tr>
    <td class="c1"><span class="rot">Total das Retenções (ISSQN / Federais)</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">VALOR LÍQUIDO DO RECIBO</span><span class="val" style="font-weight: bold;">R$ {{ $valor }}</span></td>
    <td class="c1"><span class="rot">Total do IBS/CBS</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">VALOR LÍQUIDO + IBS/CBS</span><span class="val">R$ {{ $valor }}</span></td>
</tr></table>

@if ($pagamento['forma'] === 'pix')
    <table class="bloco"><tr><td class="c4 titulo">PAGAMENTO</td></tr></table>
    <table><tr>
        <td class="c1" style="text-align: center;"><img src="{{ $pagamento['qr'] }}" alt="" style="width: 70pt; height: 70pt;"></td>
        <td class="c3">
            <span class="rot">Forma de Pagamento</span><span class="val">PIX — {{ $pagamento['tipo'] }}</span>
            <span class="rot">Chave</span><span class="val">{{ $pagamento['chave'] }}</span>
            <span class="rot">Favorecido</span><span class="val">{{ $pagamento['favorecido'] }}</span>
        </td>
    </tr></table>
    {{-- Largura total e uma linha só (quebra de linha estraga a cópia do código no celular). --}}
    <table><tr><td class="c4">
        <span class="rot">PIX Copia e Cola</span>
        <span class="val" style="white-space: nowrap; font-size: {{ min(5.5, round(540 / (strlen($pagamento['copia_e_cola']) * 0.62), 2)) }}pt;">{{ $pagamento['copia_e_cola'] }}</span>
    </td></tr></table>
@elseif ($pagamento['forma'] === 'boleto')
    <table class="bloco"><tr><td class="c4 titulo">PAGAMENTO</td></tr></table>
    <table><tr><td class="c4">
        <span class="rot">Forma de Pagamento</span><span class="val">Boleto bancário</span>
        @if ($pagamento['linha_digitavel'])
            <span class="rot">Linha Digitável</span><span class="val">{{ $pagamento['linha_digitavel'] }}</span>
        @endif
    </td></tr></table>
@endif

<table class="bloco"><tr><td class="c4 titulo">INFORMAÇÕES COMPLEMENTARES</td></tr></table>
<table><tr><td class="c4" style="height: 60pt;">
    @foreach ($informacoes as $linha)
        <div class="val">{{ $linha }}</div>
    @endforeach
    <div class="val" style="margin-top: 4pt;">Este recibo não substitui a Nota Fiscal de Serviços Eletrônica. Gerado em {{ $gerado_em }}.</div>
</td></tr></table>

<div class="rodape">
    <table><tr>
        <td style="width: 30%;"><span class="rot">DATA DO RECEBIMENTO:</span></td>
        <td style="width: 35%;"><span class="rot">IDENTIFICAÇÃO E ASSINATURA DO PRESTADOR</span></td>
        <td style="width: 35%;"><span class="rot">Nº DO RECIBO</span><span class="val">{{ $numero }}</span></td>
    </tr></table>
</div>
</body>
</html>
