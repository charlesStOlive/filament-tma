<?php

namespace CharlesStOlive\FilamentTma\Http\Middleware;

use CharlesStOlive\FilamentTma\Models\Instance;
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
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tma::isMaster(), 404);

        $user = $request->user(config('filament-tma.api.guard', 'api'));
        abort_if($user === null, 401);
        abort_if(Instance::query()->where('api_user_id', $user->getAuthIdentifier())->exists(), 403);

        return $next($request);
    }
}
