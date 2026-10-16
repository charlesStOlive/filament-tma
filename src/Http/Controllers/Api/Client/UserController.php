<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET users` : les correspondants du Support de cette application, ceux qui ont le droit d'écrire au Support
 * (`tma.ticket.correspond`) — les seuls que le maître peut nommer demandeur d'un ticket préparé ou destinataire. Jamais
 * les membres du Support ni le compte du maître.
 */
class UserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $users = [];

        Tma::userModel()::query()->orderBy('name')->each(function ($user) use (&$users, $request): void {
            if ($user->getAuthIdentifier() === $request->user()?->getAuthIdentifier()) {
                return; // le compte du maître lui-même
            }

            if (Addressables::isCorrespondent($user)) {
                $users[] = [
                    'id' => (string) $user->getAuthIdentifier(),
                    'name' => (string) ($user->name ?? $user->email),
                    'email' => $user->email ?? null,
                    'service_desk' => true,
                ];
            }
        });

        return response()->json(['data' => $users]);
    }
}
