<?php

namespace CharlesStOlive\FilamentTma\Support;

use Filament\Facades\Filament;

/**
 * L'habillage des courriels du module (`resources/views/mail`) : le logo de l'application, la couleur d'un ticket et
 * celle d'une tâche (`filament-tma.mail`, sinon les couleurs de filament-ui), et de quoi rester lisible partout — une
 * couleur de texte assez contrastée sur la couleur, une teinte claire pour les encadrés.
 */
final class MailBrand
{
    private const FALLBACK = ['ticket' => '#2563EB', 'task' => '#475569'];

    /** Les polices des courriels : celles du système, Arial en dernier recours (Outlook). */
    public const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    /**
     * Le logo, à 40 px de haut : son adresse (toujours celle de `app.url`, joignable depuis une messagerie), sa
     * largeur et sa hauteur (Outlook a besoin des deux). Null : pas de logo, le nom de l'application.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public static function logo(int $height = 40): ?array
    {
        $logo = (string) config('filament-tma.mail.logo');

        if ($logo === '') {
            return null;
        }

        if (preg_match('#^https?://#', $logo)) {
            return ['url' => $logo, 'width' => $height * 3, 'height' => $height];
        }

        $path = public_path(ltrim($logo, '/'));
        $size = is_file($path) ? @getimagesize($path) : false;

        if ($size === false || $size[1] === 0) {
            return null;
        }

        return [
            'url' => rtrim((string) config('app.url'), '/').'/'.ltrim($logo, '/'),
            'width' => (int) round($size[0] * $height / $size[1]),
            'height' => $height,
        ];
    }

    public static function name(): string
    {
        return (string) config('app.name');
    }

    /** La couleur d'un ticket (`ticket`) ou d'une tâche (`task`), en `#RRGGBB`. */
    public static function color(string $kind): string
    {
        $kind = $kind === 'task' ? 'task' : 'ticket';

        foreach ([config("filament-tma.mail.colors.{$kind}"), config('filament-ui.colors.'.($kind === 'task' ? 'secondary' : 'primary'))] as $color) {
            if (is_string($color) && preg_match('/^#?([0-9a-f]{6})$/i', trim($color), $match)) {
                return '#'.strtoupper($match[1]);
            }
        }

        return self::FALLBACK[$kind];
    }

    /** Le texte le plus lisible sur cette couleur : blanc, ou presque noir. */
    public static function onColor(string $hex): string
    {
        $dark = '#111827';
        $luminance = self::luminance($hex);

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / (self::luminance($dark) + 0.05)) ? '#FFFFFF' : $dark;
    }

    /** La couleur, éclaircie vers le blanc (`$white` : la part de blanc), pour le fond d'un encadré. */
    public static function tint(string $hex, float $white = 0.9): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf('#%02X%02X%02X', ...array_map(fn (int $c): int => (int) round($c + (255 - $c) * $white), [$r, $g, $b]));
    }

    /** Une version de la couleur assez sombre pour un texte sur fond blanc (les titres des encadrés). */
    public static function ink(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        while (self::contrast(sprintf('#%02X%02X%02X', $r, $g, $b), '#FFFFFF') < 4.5 && ($r + $g + $b) > 0) {
            [$r, $g, $b] = array_map(fn (int $c): int => (int) floor($c * 0.85), [$r, $g, $b]);
        }

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /** L'adresse de la section des notifications du profil, où chacun règle les siennes. */
    public static function preferencesUrl(): ?string
    {
        if (! NoticeChannels::userChoice()) {
            return null;
        }

        $url = rescue(fn (): ?string => Filament::getCurrentOrDefaultPanel()?->getProfileUrl(), null, false);

        return $url !== null ? $url.'#user-options-tma' : null;
    }

    /** @return array{logo: array{url: string, width: int, height: int}|null, name: string, color: string, on: string, tint: string, ink: string, preferences: ?string, font: string} */
    public static function for(string $kind): array
    {
        $color = self::color($kind);

        return [
            'logo' => self::logo(),
            'name' => self::name(),
            'color' => $color,
            'on' => self::onColor($color),
            'tint' => self::tint($color),
            'ink' => self::ink($color),
            'preferences' => self::preferencesUrl(),
            'font' => self::FONT,
        ];
    }

    private static function contrast(string $a, string $b): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
