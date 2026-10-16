<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Models\Instance;
use Illuminate\Support\Facades\Cache;

/**
 * Les correspondants du Support d'une application cliente (`GET users` : le droit d'écrire au Support), gardés en
 * cache `api.users_cache_ttl` secondes (addendum §5).
 */
final class RemoteUsers
{
    /** @return array<int, array{id: string, name: string, email: string|null, service_desk: bool}> */
    public static function for(Instance $instance): array
    {
        return Cache::remember(
            self::key($instance),
            (int) config('filament-tma.api.users_cache_ttl', 3600),
            fn (): array => (new InstanceClient($instance))->users(),
        );
    }

    public static function forget(Instance $instance): void
    {
        Cache::forget(self::key($instance));
    }

    private static function key(Instance $instance): string
    {
        return 'filament-tma.users.'.$instance->uid;
    }
}
