<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * `GET users` : les utilisateurs de cette application qui ont des droits sur le centre de service, c'est-à-dire
 * qui peuvent transmettre un ticket (`tma.ticket.workflow.transmit`). Les utilisateurs « local uniquement » (qui
 * voient les tickets sans pouvoir les transmettre) ne sont donnés qu'avec `include_local_only=1`.
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
                return; // un agent du centre de service n'est pas un demandeur
            }

            $serviceDesk = TmaGate::allows($user, TmaGate::workflowAction('transmit'));
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
