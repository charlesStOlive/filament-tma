<?php

namespace CharlesStOlive\FilamentTma\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Le HTML des tickets et de la chronologie, nettoyé côté serveur avant d'être enregistré (CDC §17.2) : pas de script,
 * pas d'attribut d'événement, pas d'iframe ni de SVG, des liens http(s) ou mailto seulement, pas de `style`.
 * Les attributs de l'éditeur riche de Filament (`data-id` d'une image jointe…) sont gardés.
 *
 * Le rendu repasse de toute façon par le nettoyeur de Filament (`RichContentRenderer::toHtml()`).
 */
final class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    /** Le texte sans balises, pour un aperçu, un e-mail texte ou une sortie de commande. */
    public static function toText(?string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/li|\/h[1-6])\s*\/?>/i', "\n", (string) $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }

    /** Vide une fois nettoyé : un « <p></p> » de l'éditeur ne compte pas comme un message. */
    public static function isBlank(?string $html): bool
    {
        return self::toText(self::clean($html)) === '' && ! str_contains(self::clean($html), '<img');
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('svg')
                ->blockElement('math')
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->allowAttribute('class', allowedElements: '*')
                ->allowAttribute('data-id', allowedElements: '*')
                ->allowAttribute('data-type', allowedElements: '*')
                ->allowAttribute('data-color', allowedElements: '*')
                ->allowAttribute('width', allowedElements: 'img')
                ->allowAttribute('height', allowedElements: 'img')
                ->withMaxInputLength(500000),
        );
    }
}
