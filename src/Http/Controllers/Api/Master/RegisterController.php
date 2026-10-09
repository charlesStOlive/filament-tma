<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateInstance;
use CharlesStOlive\FilamentTma\Sync\InstanceClient;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST master/register` : une application cliente (reconnue à son jeton) transmet celui par lequel le centre de
 * service l'appellera — plus rien à recopier à la main. Le centre de service la rappelle aussitôt (`GET ping`) : la
 * réponse dit si la connexion marche aussi dans ce sens.
 */
class RegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:4000']]);

        $instance->update(['token' => $data['token']]);

        try {
            $answer = (new InstanceClient($instance->refresh()))->ping();
            $callback = ($answer['instance_uid'] ?? null) === $instance->uid;
            $error = $callback ? null : __('L’application répond, mais avec un autre identifiant (:uid).', ['uid' => $answer['instance_uid'] ?? '?']);
        } catch (SyncException $e) {
            $callback = false;
            $error = __(':message Vérifiez l’adresse de l’application dans le centre de service (:url).', ['message' => $e->getMessage(), 'url' => $instance->url]);
        }

        return response()->json([
            'instance' => $instance->name,
            'callback' => $callback,
            'callback_error' => $error,
        ]);
    }
}
