<?php

namespace App\Modules\Ticketing\Actions;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a QR code as an inline SVG string from a textual payload.
 *
 * The SVG is returned without XML prolog so it can be embedded directly
 * inside a Blade template (and consumed by Dompdf for PDF generation).
 */
final class GenerateTicketQrCodeAction
{
    /**
     * @param  string  $payload  The signed token to encode.
     * @param  int  $size  Pixel size of the rendered QR code (default 200).
     * @return string Inline SVG markup (no XML declaration).
     */
    public function execute(string $payload, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, margin: 2),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($payload);

        // Strip the XML prolog so the SVG can be inlined in HTML/Blade.
        return trim(preg_replace('/^<\?xml[^>]*>\s*/i', '', $svg) ?? $svg);
    }
}
