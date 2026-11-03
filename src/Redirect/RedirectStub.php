<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Redirect;

/**
 * The page left at an old address: a redirect that works without the server's rules (a target other than Apache, a
 * directory opened locally). Search engines take the canonical link as the new address.
 */
final class RedirectStub
{
    public static function html(string $relative, string $absolute): string
    {
        $r = htmlspecialchars($relative, ENT_QUOTES);
        $a = htmlspecialchars($absolute, ENT_QUOTES);
        $js = json_encode($relative, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="pl">
            <head>
            <meta charset="UTF-8">
            <title>Przeniesiono</title>
            <meta name="robots" content="noindex">
            <link rel="canonical" href="{$a}">
            <meta http-equiv="refresh" content="0; url={$r}">
            <script>location.replace({$js} + location.hash);</script>
            </head>
            <body><p>Ta strona jest teraz pod adresem <a href="{$r}">{$a}</a>.</p></body>
            </html>

            HTML;
    }
}
