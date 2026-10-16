<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * `GET users` : les correspondants du Support de cette application, ceux qui ont le droit d'écrire au Support
 * (`tma.ticket.workflow.transmit`) — les seuls que le maître peut nommer demandeur d'un ticket préparé ou
 * destinataire. Les internes (qui voient les tickets sans ce droit) ne sont donnés qu'avec `include_local_only=1`,
 * `service_desk` à faux. Jamais les membres du Support ni le compte du maître.
 */
class UserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $includeLocalOnly = $request->boolean('include_local_only');
        $model = Tma::userModel();
        $hasPolicy = Gate::getPolicyFor(Ticket::class) !== null;
        $users = [];

        $model::query()->orderBy('name')->each(function ($user) use (&$users, $includeLocalOnly, $hasPolicy): void {
            if ($user->getAuthIdentifier() === request()->user()?->getAuthIdentifier()) {
                return; // le compte du maître lui-même
            }

            if (Agent::for($user) !== null) {
                return; // un membre du Support n'est pas un demandeur
            }

            $serviceDesk = Addressables::isCorrespondent($user);
            $seesTickets = ! $hasPolicy || Gate::forUser($user)->allows('viewAny', Ticket::class);

            if ($serviceDesk || ($includeLocalOnly && $seesTickets)) {
                $users[] = [
                    'id' => (string) $user->getAuthIdentifier(),
                    'name' => (string) ($user->name ?? $user->email),
                    'email' => $user->email ?? null,
                    'service_desk' => $serviceDesk,
                ];
            }
        });

        return response()->json(['data' => $users]);
    }
}
