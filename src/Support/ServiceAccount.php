<?php

namespace CharlesStOlive\FilamentTma\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use RuntimeException;

/**
 * Les comptes de service par lesquels les applications s'appellent : un utilisateur sans rôle (il n'entre dans aucun
 * panel) et son jeton Passport. Un nouveau jeton révoque le précédent du même nom : il n'y en a qu'un valide.
 */
final class ServiceAccount
{
    public const TOKEN_NAME = 'tma-service';

    /** L'utilisateur de service, créé au besoin avec un mot de passe aléatoire que personne ne connaît. */
    public static function user(string $email, string $name): Authenticatable
    {
        $model = Tma::userModel();

        $user = $model::query()->where('email', $email)->first();

        if ($user === null) {
            $user = (new $model)->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->save();
        }

        return $user;
    }

    /** Un compte qui ne peut pas servir : il existe déjà et a des rôles (une personne), ou sert déjà à une application. */
    public static function isTaken(string $email): bool
    {
        $model = Tma::userModel();
        $user = $model::query()->where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        $hasRoles = method_exists($user, 'roles') && $user->roles()->exists();

        return $hasRoles || \CharlesStOlive\FilamentTma\Models\Instance::query()->where('api_user_id', $user->getAuthIdentifier())->exists();
    }

    /** Un nouveau jeton pour ce compte ; les anciens jetons de service sont révoqués. */
    public static function token(Authenticatable $user): string
    {
        if (! method_exists($user, 'createToken')) {
            throw new RuntimeException(__('Le modèle des utilisateurs doit utiliser Laravel\Passport\HasApiTokens.'));
        }

        $user->tokens()->where('name', self::TOKEN_NAME)->where('revoked', false)->get()->each->revoke();

        self::ensurePersonalAccessClient();

        return $user->createToken(self::TOKEN_NAME)->accessToken;
    }

    /** Passport émet les jetons personnels par un « client d'accès personnel » : on le crée s'il manque. */
    private static function ensurePersonalAccessClient(): void
    {
        $provider = config('auth.guards.'.config('filament-tma.api.guard', 'api').'.provider', 'users');
        $clients = app(ClientRepository::class);

        try {
            $clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $clients->createPersonalAccessGrantClient('Jetons personnels', $provider);
        }
    }
}
