<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\NotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Comment une personne est prévenue : quand elle est destinataire principal, tout de suite par courriel, dans la
 * cloche de l'application (les notifications en base de Filament), ou les deux ; et si elle reçoit le bilan du matin
 * (ce qui l'attend, ce qui a été gardé pour elle). L'application dit ce qui est actif pour qui n'a rien choisi
 * (`notifications.defaults`) ; chacun le change pour lui (« Mes notifications »), sauf si l'application le fixe
 * (`notifications.user_choice`). Sans courriel ni cloche, ce qui le concerne attend son bilan ; sans bilan non plus,
 * il n'en reçoit rien.
 */
final class NoticeChannels
{
    public const MAIL = 'mail';

    public const APP = 'database';

    public const DIGEST = 'digest';

    /** @return array<int, string> par où un principal est prévenu tout de suite (`mail`, `database`) */
    public static function for(Authenticatable $user): array
    {
        return self::valid(self::preference($user)?->channels ?? self::defaults());
    }

    /** S'il reçoit le bilan : sinon, ce qui devait l'attendre est seulement noté (`NoticeMode::Skipped`). */
    public static function digest(Authenticatable $user): bool
    {
        return self::preference($user)?->digest ?? in_array(self::DIGEST, self::defaults(), true);
    }

    /** @return array<int, string> ce qui est actif pour qui n'a rien choisi : `mail`, `database`, `digest` */
    public static function defaults(): array
    {
        return (array) config('filament-tma.notifications.defaults', [self::MAIL, self::APP, self::DIGEST]);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::MAIL => __('Par courriel'),
            self::APP => __('Dans l’application (la cloche)'),
        ];
    }

    /**
     * @param  array<int, string>  $channels
     * @param  bool|null  $digest  null : on n'y touche pas (le choix déjà fait, sinon le réglage de l'application)
     */
    public static function save(Authenticatable $user, array $channels, ?bool $digest = null): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => (string) $user->getAuthIdentifier()],
            ['channels' => self::valid($channels), ...($digest !== null ? ['digest' => $digest] : [])],
        );
    }

    public static function userChoice(): bool
    {
        return (bool) config('filament-tma.notifications.user_choice', true);
    }

    private static function preference(Authenticatable $user): ?NotificationPreference
    {
        return self::userChoice() ? NotificationPreference::of($user) : null;
    }

    /**
     * @param  array<int, mixed>  $channels
     * @return array<int, string>
     */
    private static function valid(array $channels): array
    {
        return array_values(array_intersect([self::MAIL, self::APP], $channels));
    }
}
