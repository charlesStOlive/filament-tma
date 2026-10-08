<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api;

use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\JsonResponse;

/** `GET ping` : qui répond, et dans quelle version de l'API. Sert au test de connexion. */
class PingController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'api' => 'v1',
            'mode' => Tma::mode(),
            'instance_uid' => Tma::instanceUid(),
            'name' => Tma::instanceName(),
        ]);
    }
}
