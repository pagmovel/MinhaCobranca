<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18pt; }
    body { margin: 0; font-size: 11pt; }
    .dejavu { font-family: 'DejaVu Sans', sans-serif; }
    .helvetica { font-family: Helvetica, sans-serif; }
    .caixa { position: relative; height: 220pt; border: 0.75pt solid #000; margin-top: 10pt; }
    .carimbo { position: absolute; top: 80pt; left: 120pt; border: 3pt solid #2E7D4F; color: #2E7D4F;
               font-weight: bold; font-size: 28pt; padding: 4pt 12pt; transform: rotate(-35deg); }
    .rodape { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7pt; border-top: 0.75pt solid #000; }
</style>
</head>
<body>
    <p class="dejavu">DejaVu Sans: Ação, prestação, município, informações, São João, ÇÃÕÉÍÚÂÊ — R$ 1.234,56</p>
    <p class="helvetica">Helvetica: Ação, prestação, município, informações, São João, ÇÃÕÉÍÚÂÊ — R$ 1.234,56</p>
    <p class="helvetica" style="font-weight: bold;">Helvetica negrito: RECIBO DE PRESTAÇÃO DE SERVIÇOS</p>
    <p class="dejavu">Imagem em data URI (quadrado vermelho de 1 px ampliado):
        <img alt="" style="width: 20pt; height: 20pt;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==">
    </p>
    <div class="caixa dejavu">
        Caixa com position: relative — o carimbo deve aparecer girado dentro dela.
        <div class="carimbo">PAGO</div>
    </div>
    <div class="rodape dejavu">Rodapé fixo: deve ficar no pé da página.</div>
</body>
</html>
