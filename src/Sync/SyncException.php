<?php

namespace CharlesStOlive\FilamentTma\Sync;

use Illuminate\Http\Client\Response;
use RuntimeException;

/** Un échange avec l'autre application a échoué. Le message est lisible par un utilisateur. */
class SyncException extends RuntimeException
{
    public static function fromResponse(string $what, Response $response): self
    {
        $detail = $response->json('message') ?? $response->reason();

        return new self(__('filament-tma::sync.errors.status', [
            'what' => $what,
            'status' => $response->status(),
            'detail' => $detail,
        ]));
    }

    public static function unreachable(string $what, \Throwable $previous): self
    {
        return new self(__('filament-tma::sync.errors.no_answer', ['what' => $what]), 0, $previous);
    }
}
