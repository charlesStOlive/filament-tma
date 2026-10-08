<?php

namespace CharlesStOlive\FilamentTma\Http\Middleware;

use CharlesStOlive\FilamentTma\Support\Tma;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Côté client : l'appelant est le maître, c'est-à-dire l'utilisateur d'ici dont il détient le jeton
 * (`filament-tma.api.master_user`, un id ou un e-mail). Personne d'autre n'appelle l'API du client.
 */
class AuthenticateMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tma::isClient(), 404);

        $user = $request->user(config('filament-tma.api.guard', 'api'));
        abort_if($user === null, 401);

        $expected = (string) config('filament-tma.api.master_user');
        $matches = $expected !== '' && in_array($expected, [(string) $user->getAuthIdentifier(), (string) ($user->email ?? '')], true);
        abort_unless($matches, 403, 'Ce jeton n’est pas celui du maître.');

        return $next($request);
    }
}
