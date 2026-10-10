<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Models\Instance;
use Illuminate\Support\Facades\Cache;

/**
 * Les utilisateurs d'une application cliente qui ont des droits sur le Support (`GET users`), gardés en
 * cache `api.users_cache_ttl` secondes (addendum §5). Les utilisateurs « local uniquement » n'y sont pas, sauf
 * demande explicite.
 */
final class RemoteUsers
{
    /** @return array<int, array{id: string, name: string, email: string|null, service_desk: bool}> */
    public static function for(Instance $instance, bool $includeLocalOnly = false): array
    {
        return Cache::remember(
            self::key($instance, $includeLocalOnly),
            (int) config('filament-tma.api.users_cache_ttl', 3600),
            fn (): array => (new InstanceClient($instance))->users($includeLocalOnly),
        );
    }

    public static function forget(Instance $instance): void
    {
        Cache::forget(self::key($instance, false));
        Cache::forget(self::key($instance, true));
    }

    private static function key(Instance $instance, bool $includeLocalOnly): string
    {
        return 'filament-tma.users.'.$instance->uid.($includeLocalOnly ? '.all' : '');
    }
}
