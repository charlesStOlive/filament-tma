<?php

namespace CharlesStOlive\FilamentTma\Commands\Concerns;

use CharlesStOlive\FilamentTma\Cli\Backend;
use CharlesStOlive\FilamentTma\Cli\LocalBackend;
use CharlesStOlive\FilamentTma\Cli\RemoteBackend;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Les commandes `tma:*`, pensées pour être lancées par une IA (aucune question quand les options sont données,
 * `--json` pour une sortie lisible par programme).
 *
 * Protection : chaque appel exige le jeton personnel d'un utilisateur (`--token`, ou `TMA_CLI_TOKEN` dans .env,
 * jamais versionné). Dans le maître, ou dans un client avec `--local`, la commande agit ici au nom de cet utilisateur,
 * avec ses droits ; dans un client, elle agit dans le maître, par son API « agent » (le jeton est alors un jeton du
 * maître). Tout est enregistré avec l'origine « commande » ou « API ».
 */
trait UsesTmaBackend
{
    /** @var (\Closure(string): ?Authenticatable)|null pour les tests */
    public static ?\Closure $resolveUserUsing = null;

    protected function backendOptions(): string
    {
        return '{--token= : Jeton personnel (sinon TMA_CLI_TOKEN)}
            {--local : Dans une application cliente, agir sur ses tickets locaux plutôt que dans le maître}
            {--json : Sortie JSON}';
    }

    protected function backend(): Backend
    {
        $token = $this->option('token') ?: (string) (getenv('TMA_CLI_TOKEN') ?: config('filament-tma.cli.token', ''));

        if ($token === '') {
            throw new RuntimeException(__('Jeton manquant : --token, ou TMA_CLI_TOKEN dans .env.'));
        }

        if (Tma::isMaster() || $this->option('local')) {
            $user = $this->userFromToken($token) ?? throw new RuntimeException(__('Jeton invalide ou expiré.'));

            return new LocalBackend($user);
        }

        if (! Tma::serviceDeskAvailable()) {
            throw new RuntimeException(__('Le maître n’est pas configuré (TMA_MASTER_URL).'));
        }

        return new RemoteBackend($token);
    }

    private function userFromToken(string $token): ?Authenticatable
    {
        if (static::$resolveUserUsing !== null) {
            return (static::$resolveUserUsing)($token);
        }

        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();

        return Auth::guard(config('filament-tma.api.guard', 'api'))->setRequest($request)->user();
    }

    /**
     * Lance l'appel ; une erreur attendue (droit, donnée, ticket introuvable, échange) s'affiche proprement.
     *
     * @param  \Closure(Backend): array<mixed>  $call
     * @param  \Closure(array<mixed>): void  $render
     */
    protected function runWithBackend(\Closure $call, \Closure $render): int
    {
        try {
            $result = $call($this->backend());
        } catch (AuthorizationException|InvalidArgumentException|RuntimeException|SyncException $e) {
            return $this->failWith($e->getMessage());
        } catch (ValidationException $e) {
            return $this->failWith(collect($e->errors())->flatten()->implode(' '));
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $render($result);
        }

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['error' => $message], JSON_UNESCAPED_UNICODE));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
