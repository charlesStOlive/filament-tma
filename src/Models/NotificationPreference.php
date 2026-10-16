<?php

namespace CharlesStOlive\FilamentTma\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Comment une personne veut être prévenue quand elle est destinataire principal (`channels` : `mail`, `database` —
 * la cloche de l'application). Sans choix, le réglage de l'application (`Support\NoticeChannels`).
 *
 * @property string $user_id
 * @property array<int, string>|null $channels
 */
class NotificationPreference extends Model
{
    protected $table = 'tma_notification_preferences';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['channels' => 'array'];
    }

    public static function of(Authenticatable $user): ?self
    {
        return static::query()->where('user_id', (string) $user->getAuthIdentifier())->first();
    }
}
