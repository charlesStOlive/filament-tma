<?php

namespace CharlesStOlive\FilamentTma\Sync;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http as LaravelHttp;

/** Les appels d'une application à l'autre : JSON, jeton, délai, erreurs lisibles (`SyncException`). */
final class Http
{
    public static function to(string $token): PendingRequest
    {
        return LaravelHttp::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout((int) config('filament-tma.api.timeout', 15));
    }

    /** @param  \Closure(): Response  $call */
    public static function call(string $what, \Closure $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw SyncException::unreachable($what, $e);
        }

        if (! $response->successful()) {
            throw SyncException::fromResponse($what, $response);
        }

        return $response;
    }
}
