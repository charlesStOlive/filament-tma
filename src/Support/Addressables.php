<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Les personnes qu'on peut nommer destinataires d'un ticket ou d'une tâche, et leur clé dans les formulaires.
 *
 * Côté client, seuls comptent les correspondants du Support (`tma.ticket.correspond`, addendum §2) : ils font les
 * demandes et sont adressables des deux côtés.
 *
 * - Dans une application cliente : ses correspondants (`u:{id}`) ; le Support : son compte passerelle (`support`, celui
 *   avec lequel le maître appelle l'application, `api.master_user`), que le maître traduit en ses destinataires par
 *   défaut (`recipients.support_defaults`), et les membres du Support reliés ici que l'application autorise
 *   (`recipients.support_members`, `m:{id dans le maître}`).
 * - Dans le maître : ses membres du Support (`tma.ticket.handle`, `u:{id}`) et les correspondants de l'application
 *   cliente (`GET users`, `c:{id chez le client}`).
 */
final class Addressables
{
    public const SUPPORT = 'support';

    /**
     * Les personnes possibles, par groupe, et celles qui sont déjà destinataires.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(?string $clientInstanceUid, ?Trackable $subject = null): array
    {
        $groups = Tma::isMaster() ? self::forMaster($clientInstanceUid) : self::forClient();
        $known = array_merge(...array_values($groups));

        foreach ($subject?->recipients()->with('actor')->get() ?? [] as $recipient) {
            $key = self::keyFor($recipient->actor);

            if (! array_key_exists($key, $known)) {
                $groups[__('filament-tma::recipients.others')][$key] = self::label($recipient->actor);
            }
        }

        return array_filter($groups);
    }

    /**
     * Client : les contacts possibles au Support, à la transmission d'une demande : les membres du Support reliés à cette
     * application, par leur id dans le maître.
     *
     * @return array<string, string>
     */
    public static function supportContacts(): array
    {
        return Agent::query()->orderBy('name')->get()
            ->mapWithKeys(fn (Agent $agent): array => [(string) $agent->master_user_id => (string) $agent->name])
            ->all();
    }

    /**
     * Le contact au Support choisi (son id dans le maître) ; sans choix, le seul membre relié s'il n'y en a qu'un.
     * Null : le Support le choisira lui-même.
     */
    public static function supportContact(?string $masterUserId): ?Actor
    {
        $agents = Agent::query()->get();
        $agent = filled($masterUserId)
            ? $agents->first(fn (Agent $agent): bool => (string) $agent->master_user_id === (string) $masterUserId)
            : ($agents->count() === 1 ? $agents->first() : null);

        return $agent === null ? null : Actor::fromInstance($agent->master_uid, (string) $agent->master_user_id, (string) $agent->name, $agent->email);
    }

    /**
     * Toutes les clés d'une liste groupée (le bouton « Tout le monde »), sauf le Support.
     *
     * @param  array<string, array<string, string>>  $options
     * @return array<int, string>
     */
    public static function everyone(array $options): array
    {
        return collect($options)->except(Terms::support())->flatMap(fn (array $group): array => array_keys($group))->values()->all();
    }

    /** L'acteur d'une clé ; null si la personne n'est plus là. */
    public static function resolve(string $key, ?string $clientInstanceUid): ?Actor
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);
        $users = Tma::userModel();

        return match ($kind) {
            self::SUPPORT => ($gateway = self::gateway()) !== null ? Actor::forUser($gateway) : null,
            'u' => ($user = $users::query()->find($id)) !== null ? Actor::forUser($user) : null,
            'm' => self::supportMember((string) $id),
            'c' => self::clientUser($clientInstanceUid, (string) $id),
            default => null,
        };
    }

    /** La clé d'une personne déjà destinataire. */
    public static function keyFor(Actor $actor): string
    {
        if ($actor->isLocal()) {
            return self::isGateway($actor) ? self::SUPPORT : 'u:'.$actor->user_id;
        }

        return (Tma::isClient() ? 'm:' : 'c:').$actor->remote_id;
    }

    /** Le compte passerelle de cette application cliente : il tient lieu du Support. */
    public static function gateway(): ?Authenticatable
    {
        $email = config('filament-tma.api.master_user');

        return Tma::isClient() && filled($email) ? Tma::userModel()::query()->where('email', $email)->first() : null;
    }

    public static function isGateway(?Actor $actor): bool
    {
        $gateway = self::gateway();

        return $actor !== null && $gateway !== null && $actor->isLocal() && (string) $actor->user_id === (string) $gateway->getAuthIdentifier();
    }

    /** Le nom d'une personne, et d'où elle vient si elle n'est pas d'ici. */
    public static function label(Actor $actor): string
    {
        if (self::isGateway($actor)) {
            return __('filament-tma::recipients.whole_support', ['support' => Terms::support()]);
        }

        return $actor->team() !== null ? "{$actor->name} ({$actor->team()})" : $actor->name;
    }

    /**
     * Correspondant du Support : le droit d'écrire au Support (`tma.ticket.correspond`). Un membre du Support relié ici
     * ne l'est jamais : il fait partie du Support.
     */
    public static function isCorrespondent(?Authenticatable $user): bool
    {
        return $user !== null && Agent::for($user) === null && TmaGate::allows($user, TmaGate::CORRESPOND);
    }

    /** Membre du Support (dans le maître) : le droit de traiter les demandes (`tma.ticket.handle`). */
    public static function isSupportMember(?Authenticatable $user): bool
    {
        return $user !== null && Tma::isMaster() && TmaGate::allows($user, TmaGate::HANDLE);
    }

    /** @return array<string, string> les membres du Support, dans le maître */
    private static function supportPeople(): array
    {
        return Tma::userModel()::query()->orderBy('name')->lazy()
            ->filter(fn (Authenticatable $user): bool => self::isSupportMember($user))
            ->mapWithKeys(fn (Authenticatable $user): array => ['u:'.$user->getAuthIdentifier() => (string) $user->name])
            ->all();
    }

    /** @return array<string, array<string, string>> */
    private static function forClient(): array
    {
        $support = [];

        if (Tma::serviceDeskAvailable()) {
            if (self::gateway() !== null) {
                $support[self::SUPPORT] = __('filament-tma::recipients.whole_support', ['support' => Terms::support()]);
            }

            foreach (self::supportMembers() as $agent) {
                $support['m:'.$agent->master_user_id] = $agent->user->name.' ('.Terms::support().')';
            }
        }

        $correspondents = Tma::userModel()::query()->orderBy('name')->lazy()
            ->filter(fn (Authenticatable $user): bool => (string) $user->getAuthIdentifier() !== (string) self::gateway()?->getAuthIdentifier()
                && self::isCorrespondent($user))
            ->mapWithKeys(fn (Authenticatable $user): array => ['u:'.$user->getAuthIdentifier() => (string) $user->name])
            ->all();

        return [Terms::support() => $support, Terms::correspondents() => $correspondents];
    }

    /** @return array<string, array<string, string>> */
    private static function forMaster(?string $clientInstanceUid): array
    {
        $correspondents = collect(self::clientUsers($clientInstanceUid))
            ->mapWithKeys(fn (array $user): array => ['c:'.$user['id'] => $user['name'].($user['email'] ? ' — '.$user['email'] : '')])
            ->all();

        return [Terms::support() => self::supportPeople(), Terms::correspondents($clientInstanceUid) => $correspondents];
    }

    /**
     * Les membres du Support reliés ici que l'application permet de nommer.
     *
     * @return iterable<Agent>
     */
    private static function supportMembers(): iterable
    {
        $emails = array_filter((array) config('filament-tma.recipients.support_members', []));

        return $emails === [] ? [] : Agent::query()->with('user')->get()
            ->filter(fn (Agent $agent): bool => $agent->user !== null && in_array($agent->user->email, $emails, true));
    }

    private static function supportMember(string $masterUserId): ?Actor
    {
        $agent = collect(self::supportMembers())->first(fn (Agent $agent): bool => (string) $agent->master_user_id === $masterUserId);

        return $agent === null ? null : Actor::fromInstance($agent->master_uid, $masterUserId, (string) $agent->user->name, $agent->user->email);
    }

    /** @return array<int, array{id: string, name: string, email: string|null, service_desk?: bool}> */
    private static function clientUsers(?string $clientInstanceUid): array
    {
        $instance = Instance::forUid($clientInstanceUid);

        return $instance === null ? [] : rescue(fn (): array => RemoteUsers::for($instance), [], false);
    }

    private static function clientUser(?string $clientInstanceUid, string $id): ?Actor
    {
        $known = $clientInstanceUid === null ? null : Actor::query()->where('instance_uid', $clientInstanceUid)->where('remote_id', $id)->first();
        $user = collect(self::clientUsers($clientInstanceUid))->firstWhere('id', $id);

        if ($user === null) {
            return $known;
        }

        return Actor::fromInstance((string) $clientInstanceUid, $id, (string) $user['name'], $user['email'] ?? null);
    }
}
