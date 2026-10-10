<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo {{ $numero }}</title>
<style>
    @page { margin: 16pt 18pt 30pt; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; color: #000; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { vertical-align: top; padding: 1.5pt 4pt; overflow: hidden; }
    .moldura { position: relative; border: 0.75pt solid #000; }
    .divisa { border-top: 0.75pt solid #000; }
    .secao { padding: 4pt 4pt 1pt; font-size: 7pt; font-weight: bold; }
    .rot { display: block; font-size: 5pt; }
    .val { display: block; font-size: 8pt; font-weight: bold; }
    .val-p { display: block; font-size: 7pt; font-weight: bold; }
    .num { text-align: right; }
    .centro { text-align: center; }
    .monograma { width: 56pt; height: 56pt; border: 1pt solid #000; font-size: 20pt; font-weight: bold; text-align: center; line-height: 56pt; }
    .carimbo { position: absolute; border: 3pt solid; padding: 4pt 12pt; font-weight: bold; text-align: center; }
    .carimbo-detalhe { font-size: 9pt; }
    .carimbo-pago { color: #2E7D4F; border-color: #2E7D4F; }
    .carimbo-a_vencer { color: #2F3C9E; border-color: #2F3C9E; }
    .carimbo-vencida { color: #B3261E; border-color: #B3261E; }
    .carimbo-vence_hoje { color: #C2410C; border-color: #C2410C; }
    .carimbo-cancelada { color: #6B7280; border-color: #6B7280; }
    .rodape { position: fixed; bottom: -21pt; left: 0; right: 0; font-size: 6pt; }
</style>
</head>
<body>
<div class="moldura">
    {{-- Cabeçalho: no original, brasão e prefeitura. Aqui, o prestador. --}}
    <table>
        <tr>
            <td style="width: 13%; height: 76pt;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="max-width: 64pt; max-height: 76pt;">
                @else
                    <div class="monograma">{{ $prestador['iniciais'] }}</div>
                @endif
            </td>
            <td style="width: 66%;">
                <div style="font-size: 12pt; font-weight: bold;">{{ $prestador['nome'] }}</div>
                <div style="margin-top: 3pt; font-size: 10pt;">{{ $prestador['nome_fantasia'] }}</div>
                <div style="margin-top: 5pt; font-size: 7.5pt; line-height: 1.35;">
                    {{ $prestador['endereco_completo'] }}<br>
                    Tel.: {{ $prestador['telefone'] }} - E-mail: {{ $prestador['email'] }} - CPF/CNPJ: {{ $prestador['documento'] }}
                </div>
            </td>
            <td style="width: 21%; padding: 4pt;">
                <div class="centro" style="border: 0.75pt solid #000; padding: 6pt;">
                    <span class="rot">Recibo nº</span>
                    <span style="display: block; font-size: 13pt; font-weight: bold;">{{ $numero }}</span>
                    <span class="rot" style="margin-top: 8pt;">Data de emissão</span>
                    <span class="val-p">{{ $emitido_em }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="divisa centro" style="padding: 5pt; font-size: 10pt; font-weight: bold;">RECIBO DE PRESTAÇÃO DE SERVIÇOS</div>

    <table class="divisa">
        <tr>
            <td style="width: 19%;"><span class="rot">Emissão (Horário de Brasília)</span><span class="val-p">{{ $emitido_em }}</span></td>
            <td style="width: 30%;"><span class="rot">Período de Competência</span><span class="val">{{ $competencia }}</span></td>
            <td><span class="rot">Município de Prestação do Serviço</span><span class="val">{{ $prestador['cidade_uf'] }}</span></td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width: 19%;"><span class="rot">Vencimento</span><span class="val">{{ $vencimento }}</span></td>
            <td><span class="rot">Situação</span><span class="val">{{ $situacao }}</span></td>
        </tr>
    </table>

    <div class="divisa secao">PRESTADOR DE SERVIÇOS</div>
    <table><tr><td><span class="rot">Razão Social</span><span class="val">{{ $prestador['nome'] }}</span></td></tr></table>
    <table>
        <tr>
            <td style="width: 61%;"><span class="rot">Nome Fantasia</span><span class="val">{{ $prestador['nome_fantasia'] }}</span></td>
            <td><span class="rot">Email</span><span class="val-p">{{ $prestador['email'] }}</span></td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width: 23%;"><span class="rot">CPF/CNPJ</span><span class="val">{{ $prestador['documento'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Municipal</span><span class="val">{{ $prestador['inscricao_municipal'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Estadual</span><span class="val">{{ $prestador['inscricao_estadual'] }}</span></td>
            <td style="width: 13%;"><span class="rot">Simples Nacional</span><span class="val">-</span></td>
            <td style="width: 15%;"><span class="rot">Incentivador Cultural</span><span class="val">-</span></td>
            <td><span class="rot">Fone/Fax</span><span class="val">{{ $prestador['telefone'] }}</span></td>
        </tr>
    </table>
    <table><tr><td><span class="rot">Endereço</span><span class="val-p">{{ $prestador['endereco_completo'] }}</span></td></tr></table>

    <div class="divisa secao">TOMADOR DE SERVIÇOS</div>
    <table><tr><td><span class="rot">Nome/Razão Social</span><span class="val">{{ $tomador['nome'] }}</span></td></tr></table>
    <table>
        <tr>
            <td style="width: 23%;"><span class="rot">CPF/CNPJ</span><span class="val">{{ $tomador['documento'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Municipal</span><span class="val">{{ $tomador['inscricao_municipal'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Estadual</span><span class="val">{{ $tomador['inscricao_estadual'] }}</span></td>
            <td style="width: 13%;"><span class="rot">Fone/Fax</span><span class="val">{{ $tomador['telefone'] }}</span></td>
            <td><span class="rot">E-mail</span><span class="val-p">{{ $tomador['email'] }}</span></td>
        </tr>
    </table>
    <table><tr><td><span class="rot">Endereço</span><span class="val-p">{{ $tomador['endereco_completo'] }}</span></td></tr></table>

    <div class="divisa secao">SERVIÇO PRESTADO</div>
    <table><tr><td><span style="font-size: 7pt;">{{ $servico['linha'] }}</span></td></tr></table>

    <div class="divisa secao">DESCRIÇÃO DOS SERVIÇOS</div>
    <table>
        <tr>
            <td style="height: 170pt;">
                {{-- Uma div por linha já quebrada (LinhasDoRecibo): o dompdf ignora line-height nas linhas que ele quebra. --}}
                @foreach ($servico['linhas'] as $linhaDescricao)
                    <div style="height: 8.4pt; font-size: 7pt;">{{ $linhaDescricao }}</div>
                @endforeach
                <div style="margin-top: 4pt; font-size: 7.5pt;">{{ $servico['obs_periodo'] }}</div>
            </td>
        </tr>
    </table>

    @php($comQr = $pagamento['forma'] === 'pix')
    @if ($comQr)
        <table><tr><td style="width: 78%; padding: 0;">
    @endif
    <div class="divisa secao">TRIBUTOS FEDERAIS</div>
    <table>
        <tr>
            @foreach (['INSS (R$)', 'IR (R$)', 'PIS (R$)', 'COFINS (R$)', 'CSLL (R$)', 'Outras Retenções (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
        </tr>
    </table>

    <div class="divisa secao">VALORES</div>
    <table>
        <tr>
            <td class="num"><span class="rot">Valor dos Serviços (R$)</span><span class="val">{{ $valor }}</span></td>
            @foreach (['Deduções (R$)', 'Desc. Cond. (R$)', 'Desc. Incond. (R$)', 'B.C. do ISS (R$)', 'Alíquota ISS (%)', 'ISS (R$)', 'ISS Retido (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
        </tr>
    </table>
    <table>
        <tr>
            @foreach (['B.C. do IBS/CBS (R$)', 'Alíquota IBS/CBS (%)', 'Redução IBS/CBS (%)', 'IBS (R$)', 'CBS (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
            <td class="num"><span class="rot">Valor Líquido (R$)</span><span class="val">{{ $valor }}</span></td>
            <td class="num"><span class="rot">Valor Total (R$)</span><span style="display: block; font-size: 12pt; font-weight: bold;">{{ $valor }}</span></td>
        </tr>
    </table>
    @if ($comQr)
        </td><td style="width: 22%; padding: 4pt; text-align: center; border-top: 0.75pt solid #000; border-left: 0.75pt solid #000;">
            <img src="{{ $pagamento['qr'] }}" alt="" style="width: 56pt; height: 56pt;">
            <span class="rot" style="margin-top: 2pt;">PIX — {{ $pagamento['tipo'] }}</span>
            <span style="display: block; font-size: 5.5pt; font-weight: bold;">{{ $pagamento['chave'] }}</span>
        </td></tr></table>
    @endif

    <div class="divisa secao">OUTRAS INFORMAÇÕES</div>
    <table>
        <tr>
            <td style="height: {{ $pagamento['forma'] === 'pix' ? 14 : 20 }}pt;">
                @foreach ($informacoes as $linha)
                    <div style="font-size: 6.5pt;">{{ $linha }}</div>
                @endforeach
                @if ($pagamento['forma'] === 'pix')
                    {{-- Uma linha só, sem quebra: quebrar no espaço do nome/cidade perde o espaço na cópia e invalida o código.
                         Fonte pelo comprimento (~0,6em por caractere em 520pt), no máximo 4,5pt. --}}
                    @php($linhaPix = 'PIX copia e cola: '.$pagamento['copia_e_cola'])
                    <div style="white-space: nowrap; font-size: {{ min(4.5, round(520 / (strlen($linhaPix) * 0.62), 2)) }}pt;">{{ $linhaPix }}</div>
                @elseif ($pagamento['forma'] === 'boleto')
                    <div style="font-size: 6.5pt;">Pagamento por boleto bancário{{ $pagamento['linha_digitavel'] ? ' — linha digitável: '.$pagamento['linha_digitavel'] : '' }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- No original, a marca d'água "RASCUNHO" ocupa este lugar. --}}
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 330pt; left: 150pt; width: 230pt; font-size: '.($carimbo['classe'] === 'vence_hoje' ? '22' : '30').'pt; transform: rotate(-35deg);'])
</div>

<div class="rodape">
    Gerado em: {{ $gerado_em }} | {{ $aplicativo }}<br>
    Este recibo não substitui a Nota Fiscal de Serviços Eletrônica.
</div>
</body>
</html>
