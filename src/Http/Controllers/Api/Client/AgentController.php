<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST agents` et `POST agents/unlink` : le Support relie (ou délie) un de ses agents à l'utilisateur
 * d'ici qui a le même e-mail. Relié, cet utilisateur agit pour le Support sur les tickets du Support, avec le jeton personnel reçu ici (limité à cette application).
 */
class AgentController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'master_uid' => ['required', 'string', 'size:26'],
            'master_user_id' => ['required'],
            'token' => ['required', 'string', 'min:20'],
        ]);

        $model = Tma::userModel();
        $user = $model::query()->where('email', $data['email'])->first();

        abort_if($user === null, 404, __('filament-tma::sync.errors.agent_missing', ['email' => $data['email'], 'app' => Tma::instanceName()]));

        Agent::query()->updateOrCreate(['user_id' => $user->getAuthIdentifier()], [
            'email' => $data['email'],
            'name' => $data['name'],
            'master_uid' => $data['master_uid'],
            'master_user_id' => (string) $data['master_user_id'],
            'token' => $data['token'],
            'linked_at' => now(),
        ]);

        return response()->json(['name' => $user->name ?? $data['email']]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Agent::query()->where('email', $request->input('email'))->delete();

        return response()->json(['ok' => true]);
    }
}
