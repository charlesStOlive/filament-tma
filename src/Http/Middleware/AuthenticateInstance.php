<?php

namespace CharlesStOlive\FilamentTma\Http\Middleware;

use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Tma;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Côté maître : l'appelant est une application cliente active, reconnue à l'utilisateur dont elle détient le jeton.
 * L'instance vient du jeton, jamais du contenu de la requête : c'est ce qui isole les clients entre eux.
 */
class AuthenticateInstance
{
    public const ATTRIBUTE = 'tma_instance';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tma::isMaster(), 404);

        $user = $request->user(config('filament-tma.api.guard', 'api'));
        abort_if($user === null, 401);

        $instance = Instance::query()->active()->where('api_user_id', $user->getAuthIdentifier())->first();
        abort_if($instance === null, 403, 'Aucune application cliente active pour ce jeton.');

        $instance->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->attributes->set(self::ATTRIBUTE, $instance);

        return $next($request);
    }

    public static function instance(Request $request): Instance
    {
        return $request->attributes->get(self::ATTRIBUTE) ?? abort(403);
    }
}
