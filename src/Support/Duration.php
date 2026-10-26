<?php

namespace CharlesStOlive\FilamentTma\Support;

use InvalidArgumentException;

/**
 * Les durées : stockées en minutes, saisies en minutes, heures ou jours (CDC §10.1), affichées d'ordinaire en jours et
 * heures (« 1 j 3 h »). Une journée vaut `filament-tma.time.day_minutes` (420 : 7 h).
 */
final class Duration
{
    public const MINUTES = 'minutes';

    public const HOURS = 'hours';

    public const DAYS = 'days';

    /** L'affichage en jours et heures : « 1 j 3 h 30 », « 3 h 30 », « 45 min ». */
    public const DAYS_HOURS = 'days_hours';

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

    /** « 1 j 3 h », « 2 h 30 », « 1,5 j », « 45 min » selon l'unité d'affichage (par défaut celle de la config). */
    public static function format(int $minutes, ?string $unit = null): string
    {
        $unit ??= (string) config('filament-tma.time.display_unit', self::DAYS_HOURS);

        return match ($unit) {
            self::DAYS => self::days($minutes / self::dayMinutes()),
            self::MINUTES => $minutes.' '.__('filament-tma::time.form.minutes_suffix'),
            self::HOURS => self::hours($minutes),
            default => self::daysAndHours($minutes),
        };
    }

    /** « 0,5 j », « 2 j » : un nombre de jours (une estimation). */
    public static function days(float $days): string
    {
        return self::number($days).' '.__('filament-tma::common.units.days_short');
    }

    /**
     * Une fourchette de jours : « 1–3 j », « 2 j » si les deux bornes sont égales, « jusqu'à 3 j » ou « au moins 1 j »
     * avec une seule borne ; null sans aucune.
     */
    public static function estimate(?float $min, ?float $max): ?string
    {
        return match (true) {
            $min !== null && $max !== null && $min == $max => self::days($min),
            $min !== null && $max !== null => self::number($min).'–'.self::days($max),
            $max !== null => __('filament-tma::time.estimate.up_to', ['days' => self::days($max)]),
            $min !== null => __('filament-tma::time.estimate.at_least', ['days' => self::days($min)]),
            default => null,
        };
    }

    /** « 1 j 3 h 30 » : les jours (de `day_minutes`), puis les heures et les minutes ; « 0 h » pour rien. */
    private static function daysAndHours(int $minutes): string
    {
        $days = intdiv($minutes, self::dayMinutes());
        $rest = $minutes % self::dayMinutes();

        $parts = array_filter([
            $days > 0 ? $days.' '.__('filament-tma::common.units.days_short') : null,
            match (true) {
                $rest >= 60 => self::hours($rest),
                $rest > 0 => $rest.' '.__('filament-tma::time.form.minutes_suffix'),
                default => null,
            },
        ]);

        return $parts === [] ? '0 '.__('filament-tma::time.form.hours_suffix') : implode(' ', $parts);
    }

    /** « 2 h 30 ». */
    private static function hours(int $minutes): string
    {
        return intdiv($minutes, 60).' '.__('filament-tma::time.form.hours_suffix').($minutes % 60 > 0 ? ' '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : '');
    }

    /** @return array<string, string> */
    public static function units(): array
    {
        return [self::MINUTES => __('filament-tma::time.units.minutes'), self::HOURS => __('filament-tma::common.units.hours'), self::DAYS => __('filament-tma::common.units.days')];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
    }
}
