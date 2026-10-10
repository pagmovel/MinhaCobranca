<?php

namespace App\Support;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** QR do PIX como PNG embutido (o dompdf desenha imagem sem ler disco nem rede). API da v6 da biblioteca. */
final class QrCodePix
{
    public static function dataUri(string $payload): string
    {
        $opcoes = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 5,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($opcoes))->render($payload);
    }
}
