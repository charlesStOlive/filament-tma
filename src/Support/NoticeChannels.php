<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\NotificationPreference;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Par où un destinataire principal est prévenu tout de suite : le courriel, la cloche de l'application (les
 * notifications en base de Filament), ou les deux. Chacun choisit (« Mes notifications »), sauf si l'application le
 * fixe (`notifications.user_choice`). Sans aucun des deux, ce qui le concerne attend son bilan.
 */
final class NoticeChannels
{
    public const MAIL = 'mail';

    public const APP = 'database';

    /** @return array<int, string> */
    public static function for(Authenticatable $user): array
    {
        $chosen = self::userChoice() ? NotificationPreference::of($user)?->channels : null;

        return self::valid($chosen ?? (array) config('filament-tma.notifications.channels', [self::MAIL, self::APP]));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::MAIL => __('Par courriel'),
            self::APP => __('Dans l’application (la cloche)'),
        ];
    }

    /** @param  array<int, string>  $channels */
    public static function save(Authenticatable $user, array $channels): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => (string) $user->getAuthIdentifier()],
            ['channels' => self::valid($channels)],
        );
    }

    public static function userChoice(): bool
    {
        return (bool) config('filament-tma.notifications.user_choice', true);
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
