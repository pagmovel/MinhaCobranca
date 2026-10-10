{{-- Situação lida da cobrança na hora de gerar o PDF (não faz parte do snapshot). --}}
<div class="carimbo carimbo-{{ $carimbo['classe'] }}" style="{{ $estilo }}">
    {{ $carimbo['texto'] }}
    @if ($carimbo['detalhe'])
        <div class="carimbo-detalhe">{{ $carimbo['detalhe'] }}</div>
    @endif
</div>
