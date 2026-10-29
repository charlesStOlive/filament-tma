<?php

namespace CharlesStOlive\FilamentTma\Support;

use InvalidArgumentException;

/**
 * Les durées : stockées en minutes, saisies en minutes, heures ou jours (CDC §10.1). Une somme (un projet, la TMA, un
 * forfait, un mois) s'affiche dans l'unité de `filament-tma.time.display_unit` : jours (« 2,55 j », d'office), heures
 * (« 17,5 h ») ou minutes (« 1 050 min »). Le temps d'un ticket, d'une tâche ou d'une saisie s'affiche en `AUTO`.
 * Une journée vaut `filament-tma.time.day_minutes` (420 : 7 h).
 */
final class Duration
{
    public const MINUTES = 'minutes';

    public const HOURS = 'hours';

    public const DAYS = 'days';

    /** L'unité qui s'adapte : en jours dès une journée, en heures en dessous, en minutes jusqu'à 30 min. */
    public const AUTO = 'auto';

    /** En `AUTO`, les minutes jusque-là (« 30 min » plutôt que « 0,5 h »). */
    private const AUTO_MINUTES_UP_TO = 30;

    /** Les décimales des jours et des heures : « 2,55 j », « 2,08 h ». */
    private const PRECISION = 2;

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

    /**
     * « 2,55 j », « 17,5 h », « 1 050 min » selon l'unité d'affichage (par défaut celle de la config, les jours si elle
     * n'en est pas une, comme l'ancien `days_hours`). Une durée trop petite pour les décimales s'écrit « < 0,01 j ».
     */
    public static function format(int $minutes, ?string $unit = null): string
    {
        $unit ??= (string) config('filament-tma.time.display_unit', self::DAYS);

        if ($unit === self::AUTO) {
            $unit = match (true) {
                abs($minutes) >= self::dayMinutes() => self::DAYS,
                abs($minutes) > self::AUTO_MINUTES_UP_TO => self::HOURS,
                default => self::MINUTES,
            };
        }

        return match ($unit) {
            self::MINUTES => self::number($minutes, 0).' '.__('filament-tma::time.form.minutes_suffix'),
            self::HOURS => self::decimal($minutes / 60).' '.__('filament-tma::time.form.hours_suffix'),
            default => self::decimal($minutes / self::dayMinutes()).' '.__('filament-tma::common.units.days_short'),
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

    /** @return array<string, string> */
    public static function units(): array
    {
        return [self::MINUTES => __('filament-tma::time.units.minutes'), self::HOURS => __('filament-tma::common.units.hours'), self::DAYS => __('filament-tma::common.units.days')];
    }

    /** Un nombre de jours ou d'heures : deux décimales au plus ; « < 0,01 » plutôt que « 0 » pour une durée. */
    private static function decimal(float $value): string
    {
        return $value != 0 && round(abs($value), self::PRECISION) == 0
            ? '< '.self::number(10 ** -self::PRECISION)
            : self::number($value);
    }

    /** « 2,55 », « 1 050 » : les séparateurs de la langue, sans zéro inutile après la virgule. */
    private static function number(float $value, int $precision = self::PRECISION): string
    {
        $decimal = __('filament-tma::common.units.decimal_separator');
        $number = number_format($value, $precision, $decimal, __('filament-tma::common.units.thousands_separator'));

        return $precision > 0 ? rtrim(rtrim($number, '0'), $decimal) : $number;
    }
}
