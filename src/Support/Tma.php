<?php

namespace CharlesStOlive\FilamentTma\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Ce que l'installation sait d'elle-même : son mode, son identifiant global, le maître qu'elle connaît.
 */
final class Tma
{
    public const MODE_MASTER = 'master';

    public const MODE_CLIENT = 'client';

    public static function mode(): string
    {
        return config('filament-tma.mode') === self::MODE_MASTER ? self::MODE_MASTER : self::MODE_CLIENT;
    }

    public static function isMaster(): bool
    {
        return self::mode() === self::MODE_MASTER;
    }

    public static function isClient(): bool
    {
        return self::mode() === self::MODE_CLIENT;
    }

    /** L'identifiant global de cette installation, écrit dans chaque ticket et chaque entrée créés ici. */
    public static function instanceUid(): string
    {
        $uid = config('filament-tma.instance.uid');

        if (! is_string($uid) || $uid === '') {
            throw new RuntimeException('filament-tma : TMA_INSTANCE_UID n’est pas défini (voir config/filament-tma.php).');
        }

        return $uid;
    }

    public static function instanceName(): string
    {
        return (string) config('filament-tma.instance.name');
    }

    public static function ticketPrefix(): string
    {
        return (string) config('filament-tma.instance.ticket_prefix', 'TMA');
    }

    /** Le circuit « centre de service » est ouvert : un client qui connaît son maître. */
    public static function serviceDeskAvailable(): bool
    {
        return self::isClient() && filled(config('filament-tma.master.url'));
    }

    public static function isHere(?string $instanceUid): bool
    {
        return $instanceUid !== null && $instanceUid === self::instanceUid();
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        return config('filament-tma.models.user') ?: config('auth.providers.users.model');
    }
}
