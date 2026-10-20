<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentPermissionManager\UserOptions\Contracts\HasUserOptions;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Comment une personne est prévenue (`Notifier`) :
 *
 * - la cloche de l'application (les notifications en base de Filament) : toujours, si l'application l'a
 *   (`notifications.bell`) ; personne ne la coupe ;
 * - l'e-mail direct : un courriel quand un ticket l'attend (création, la main lui revient) ;
 * - le bilan du matin : ce qui l'attend, ce qui a été gardé pour elle sur ses tickets ;
 * - le bilan de la semaine : ce qui s'est passé sur ses tâches projet.
 *
 * L'application dit ce qui est actif pour qui n'a rien choisi (`notifications.defaults`) ; chacun le change pour lui
 * dans son profil, sauf si l'application le fixe (`notifications.user_choice`). L'e-mail direct est rangé, comme avant
 * la cloche obligatoire, dans la liste `notifications.channels` (`mail`) : les choix déjà faits restent valables.
 */
final class NoticeChannels
{
    public const MAIL = 'mail';

    public const APP = 'database';

    public const DIGEST = 'digest';

    public const WEEKLY = 'weekly';

    /** La cloche : pour tout le monde, si l'application l'a. */
    public static function bell(): bool
    {
        return (bool) config('filament-tma.notifications.bell', true);
    }

    /** L'e-mail direct : un courriel quand un ticket l'attend. */
    public static function mail(Authenticatable $user): bool
    {
        return in_array(self::MAIL, (array) self::chosen($user, 'notifications.channels', self::defaultChannels()), true);
    }

    /** Le bilan du matin : sans lui, ce qui devait y aller est seulement noté (`NoticeMode::Skipped`). */
    public static function digest(Authenticatable $user): bool
    {
        return (bool) config('filament-tma.notifications.digest.enabled', true)
            && (bool) self::chosen($user, 'notifications.digest', in_array(self::DIGEST, self::defaults(), true));
    }

    /** Le bilan de la semaine des tâches projet. */
    public static function weekly(Authenticatable $user): bool
    {
        return (bool) config('filament-tma.notifications.weekly.enabled', true)
            && (bool) self::chosen($user, 'notifications.weekly', in_array(self::WEEKLY, self::defaults(), true));
    }

    /** @return array<int, string> ce qui est actif pour qui n'a rien choisi : `mail`, `digest`, `weekly` */
    public static function defaults(): array
    {
        return (array) config('filament-tma.notifications.defaults', [self::DIGEST, self::WEEKLY]);
    }

    /** @return array<int, string> la valeur de `notifications.channels` pour qui n'a rien choisi */
    public static function defaultChannels(): array
    {
        return in_array(self::MAIL, self::defaults(), true) ? [self::MAIL] : [];
    }

    public static function userChoice(): bool
    {
        return (bool) config('filament-tma.notifications.user_choice', true);
    }

    private static function chosen(Authenticatable $user, string $path, mixed $default): mixed
    {
        return self::userChoice() && $user instanceof HasUserOptions
            ? $user->userOption("tma.{$path}", $default)
            : $default;
    }
}
