<?php

namespace CharlesStOlive\FilamentTma\Support;

use InvalidArgumentException;

/**
 * Les durées : stockées en minutes, saisies et affichées en minutes, heures ou jours (CDC §10.1). Une journée vaut
 * `filament-tma.time.day_minutes` (420 : 7 h).
 */
final class Duration
{
    public const MINUTES = 'minutes';

    public const HOURS = 'hours';

    public const DAYS = 'days';

    public static function dayMinutes(): int
    {
        return max(1, (int) config('filament-tma.time.day_minutes', 420));
    }

    public static function toMinutes(float|int|string $value, string $unit): int
    {
        $value = (float) str_replace(',', '.', (string) $value);

        $minutes = match ($unit) {
            self::MINUTES => $value,
            self::HOURS => $value * 60,
            self::DAYS => $value * self::dayMinutes(),
            default => throw new InvalidArgumentException("Unité de durée inconnue : {$unit}"),
        };

        return (int) round($minutes);
    }

    /** « 2 h 30 », « 1,5 j », « 45 min » selon l'unité d'affichage (par défaut celle de la config). */
    public static function format(int $minutes, ?string $unit = null): string
    {
        $unit ??= (string) config('filament-tma.time.display_unit', self::HOURS);

        return match ($unit) {
            self::DAYS => self::number($minutes / self::dayMinutes()).' j',
            self::MINUTES => $minutes.' min',
            default => intdiv($minutes, 60).' h'.($minutes % 60 > 0 ? ' '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : ''),
        };
    }

    /** @return array<string, string> */
    public static function units(): array
    {
        return [self::MINUTES => __('minutes'), self::HOURS => __('heures'), self::DAYS => __('jours')];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }
}
