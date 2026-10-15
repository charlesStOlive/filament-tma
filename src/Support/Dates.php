<?php

namespace CharlesStOlive\FilamentTma\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Les dates qu'on affiche soi-même (vues de la chronologie, courriels), dans le fuseau de celui qui lit. Elles
 * s'enregistrent toujours dans le fuseau de l'application (UTC) ; les composants Filament (colonnes, entrées,
 * sélecteurs) convertissent seuls.
 *
 * Le fuseau : celui de la personne (`timezone` de son compte, quand l'application en a un, comme avec
 * filament-permission-manager), sinon celui que Filament affiche (`FilamentTimezone`), sinon celui de l'application.
 */
final class Dates
{
    public static function timezone(?Authenticatable $user = null): string
    {
        $user ??= auth()->user();
        $timezone = $user !== null ? ($user->timezone ?? null) : null;

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return FilamentTimezone::get();
    }

    /** Une copie de la date, dans le fuseau de la personne (l'utilisateur connecté par défaut). */
    public static function local(?DateTimeInterface $date, ?Authenticatable $user = null): ?CarbonInterface
    {
        return $date === null ? null : Carbon::instance($date)->setTimezone(self::timezone($user));
    }

    /** « 14/10/2026 00:10 », dans le fuseau de la personne. */
    public static function format(?DateTimeInterface $date, string $format = 'd/m/Y H:i', ?Authenticatable $user = null): string
    {
        return self::local($date, $user)?->format($format) ?? '';
    }
}
