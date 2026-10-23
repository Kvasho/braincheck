<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes as SVG data URIs, rendered in an <img> (never injected as markup).
 */
final class QrCode
{
    public const int SIZE = 240;

    public static function svgDataUri(string $content): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(self::SIZE, 1), new SvgImageBackEnd)))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
