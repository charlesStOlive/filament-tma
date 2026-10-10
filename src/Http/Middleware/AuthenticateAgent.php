<?php

namespace CharlesStOlive\FilamentTma\Http\Middleware;

use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use CharlesStOlive\FilamentTma\Support\Tma;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Côté maître, l'API « agent » : un utilisateur du maître, par son jeton personnel (les commandes `tma:*` lancées
 * dans une application cliente). Le compte de service d'une application cliente n'y a pas accès.
 */
class AuthenticateAgent
{
    public const SCOPE = 'tma_agent_instance_uid';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tma::isMaster(), 404);

        $user = $request->user(config('filament-tma.api.guard', 'api'));
        abort_if($user === null, 401);
        abort_if(Instance::query()->where('api_user_id', $user->getAuthIdentifier())->exists(), 403);

        // Un jeton d'agent (émis pour agir depuis une application cliente) ne vaut que pour les tickets et tâches de
        // cette application, même si la personne en voit d'autres dans le maître.
        $tokenName = method_exists($user, 'token') ? $user->token()?->name : null;

        if (is_string($tokenName) && str_starts_with($tokenName, ServiceAccount::AGENT_TOKEN_PREFIX)) {
            $request->attributes->set(self::SCOPE, substr($tokenName, strlen(ServiceAccount::AGENT_TOKEN_PREFIX)));
        }

        return $next($request);
    }
}
