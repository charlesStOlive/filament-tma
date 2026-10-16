<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Events\RecipientsChanged;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les destinataires d'un ticket ou d'une tâche : principaux (prévenus tout de suite) et secondaires (dans le bilan).
 *
 * - Chacun les change tout au long de la vie du ticket (`update()`). Dans une application cliente, sur ce dont le
 *   maître fait autorité, la liste part au maître (`fromClient()`), qui la renvoie complète (`mirror()`).
 * - Une application cliente ne nomme que ses utilisateurs, et le Support par son compte passerelle, que le maître
 *   traduit en ses destinataires par défaut (`supportDefaults()`). Elle ne retire jamais un membre du Support.
 * - Les automatismes (`add()`) : l'auteur d'un ticket ou d'une tâche, qui transmet, qui prend en charge.
 */
final class Recipients
{
    /** @return array{primary_recipients: array<int, string>, secondary_recipients: array<int, string>} les clés actuelles (`Addressables`) */
    public function keys(Trackable $subject): array
    {
        $rows = $subject->recipients()->with('actor')->get();

        return [
            'primary_recipients' => $rows->where('role', RecipientRole::Primary)->map(fn (Recipient $r): string => Addressables::keyFor($r->actor))->values()->all(),
            'secondary_recipients' => $rows->where('role', RecipientRole::Secondary)->map(fn (Recipient $r): string => Addressables::keyFor($r->actor))->values()->all(),
        ];
    }

    /**
     * Change les destinataires depuis l'interface. Une personne choisie dans les deux listes est principale ; l'auteur
     * du ticket ou de la tâche le reste toujours. Les personnes ajoutées sont prévenues (sauf l'auteur du changement).
     *
     * @param  array<int, string>  $primary  des clés d'`Addressables`
     * @param  array<int, string>  $secondary
     */
    public function update(Trackable $subject, array $primary, array $secondary, Context $context): void
    {
        $uid = $subject->clientInstanceUid();
        $roles = collect($secondary)->mapWithKeys(fn (string $key): array => [$key => RecipientRole::Secondary])
            ->merge(collect($primary)->mapWithKeys(fn (string $key): array => [$key => RecipientRole::Primary]));
        $actors = $roles->map(fn (RecipientRole $role, string $key): ?array => ($actor = Addressables::resolve($key, $uid)) ? [$actor, $role] : null)->filter()
            ->keyBy(fn (array $pair): int => (int) $pair[0]->getKey());

        // L'auteur d'ici reste principal.
        if (($creator = $subject->createdBy) !== null && $creator->isLocal()) {
            $actors->put((int) $creator->getKey(), [$creator, RecipientRole::Primary]);
        }

        $actors = $actors->values();

        if (Tma::isClient() && ! $subject->authorityIsHere()) {
            $payload = $actors->map(fn (array $pair): array => self::item($pair[0], $pair[1]))->all();
            $snapshot = app(MasterClient::class)->recipients($subject, $payload, $context);
            DB::transaction(fn () => Mirror::snapshot($snapshot));

            return;
        }

        $added = DB::transaction(fn (): array => $this->replace($subject, $actors->mapWithKeys(fn (array $pair): array => [$pair[0]->getKey() => $pair[1]])->all()));
        Notifier::recipientsAdded($subject, $added, $context->actor);
        event(new RecipientsChanged($subject, $context));
    }

    /** Ajoute une personne (les automatismes) : un secondaire devient principal, jamais l'inverse. */
    public function add(Trackable $subject, ?Actor $actor, RecipientRole $role = RecipientRole::Primary): void
    {
        if ($actor === null) {
            return;
        }

        $row = $subject->recipients()->firstOrNew(['actor_id' => $actor->getKey()]);

        if (! $row->exists || ($role === RecipientRole::Primary && $row->role !== RecipientRole::Primary)) {
            $row->role = $role;
            $row->save();
        }
    }

    /**
     * Les destinataires d'ici (qui ont un compte), avec leur rôle.
     *
     * @return Collection<int, array{user: Authenticatable, actor: Actor, role: RecipientRole}>
     */
    public function local(Trackable $subject): Collection
    {
        return $subject->recipients()->with('actor.user')->get()
            ->filter(fn (Recipient $r): bool => $r->actor?->isLocal() && $r->actor->user !== null && ! Addressables::isGateway($r->actor))
            ->map(fn (Recipient $r): array => ['user' => $r->actor->user, 'actor' => $r->actor, 'role' => $r->role])
            ->values();
    }

    /** Au moins un destinataire principal parmi ces acteurs (ceux d'une application, d'une équipe…). */
    public function hasPrimary(Trackable $subject, \Closure $among): bool
    {
        return $subject->recipients()->with('actor')->where('role', RecipientRole::Primary->value)->get()
            ->contains(fn (Recipient $r): bool => $r->actor !== null && $among($r->actor));
    }

    /**
     * La liste pour l'autre application : chaque personne, son rôle, et si elle tient lieu du Support.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function payload(Trackable $subject): array
    {
        return $subject->recipients()->with('actor')->orderBy('id')->get()
            ->map(fn (Recipient $r): array => self::item($r->actor, $r->role))
            ->all();
    }

    /**
     * Maître : la liste envoyée par une application cliente. Ses personnes à elle remplacent celles qu'on avait ; le
     * Support (son compte passerelle) devient nos destinataires par défaut ; un membre du Support nommé est ajouté.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, int> les acteurs ajoutés
     */
    public function fromClient(Trackable $subject, string $instanceUid, array $payload): array
    {
        return DB::transaction(function () use ($subject, $instanceUid, $payload): array {
            $before = $subject->recipients()->pluck('actor_id')->map(fn ($id): int => (int) $id)->all();
            $theirs = [];

            foreach ($payload as $item) {
                $role = RecipientRole::tryFrom((string) ($item['role'] ?? '')) ?? RecipientRole::Primary;

                if (! empty($item['support'])) {
                    $this->supportDefaults()->each(fn (Actor $actor) => $this->add($subject, $actor, $role));

                    continue;
                }

                $actor = Mirror::actor($item['actor'] ?? null);

                if ($actor?->instance_uid === $instanceUid) {
                    $theirs[$actor->getKey()] = $role;
                } elseif ($actor?->isLocal()) {
                    $this->add($subject, $actor, $role);
                }
            }

            $this->replace($subject, $theirs, onlyInstance: $instanceUid);
            $this->ensureSupport($subject);

            return array_values(array_diff($subject->recipients()->pluck('actor_id')->map(fn ($id): int => (int) $id)->all(), $before));
        });
    }

    /**
     * Client : la liste complète, telle que le maître la connaît.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, int> les acteurs ajoutés
     */
    public function mirror(Trackable $subject, array $payload): array
    {
        return $this->replace($subject, collect($payload)
            ->mapWithKeys(fn (array $item): array => ($actor = Mirror::actor($item['actor'] ?? null)) === null ? [] : [
                $actor->getKey() => RecipientRole::tryFrom((string) ($item['role'] ?? '')) ?? RecipientRole::Primary,
            ])->all());
    }

    /** Maître : un ticket du Support a toujours un principal chez nous (nos destinataires par défaut). */
    public function ensureSupport(Trackable $subject): void
    {
        if (Tma::isMaster() && ! $this->hasPrimary($subject, fn (Actor $actor): bool => $actor->isLocal())) {
            $this->supportDefaults()->each(fn (Actor $actor) => $this->add($subject, $actor));
        }
    }

    /**
     * Maître : qui reçoit ce qu'une application cliente adresse « au Support » — `recipients.support_defaults`
     * (des adresses), sinon les membres du Support.
     *
     * @return Collection<int, Actor>
     */
    public function supportDefaults(): Collection
    {
        $users = Tma::userModel();
        $emails = array_filter((array) config('filament-tma.recipients.support_defaults', []));
        $people = $emails !== [] ? $users::query()->whereIn('email', $emails)->get() : collect();

        if ($people->isEmpty()) {
            $people = $users::query()->lazy()->filter(fn (Authenticatable $user): bool => Addressables::isSupportMember($user))->collect();
        }

        return $people->map(fn (Authenticatable $user): Actor => Actor::forUser($user))->values();
    }

    /**
     * Remplace les destinataires (tous, ou ceux d'une application) par ceux donnés.
     *
     * @param  array<int, RecipientRole>  $roles  par acteur
     * @return array<int, int> les acteurs ajoutés
     */
    private function replace(Trackable $subject, array $roles, ?string $onlyInstance = null): array
    {
        $current = $subject->recipients()->with('actor')->get()
            ->filter(fn (Recipient $r): bool => $onlyInstance === null || $r->actor?->instance_uid === $onlyInstance)
            ->keyBy('actor_id');

        $current->reject(fn (Recipient $r, $actorId): bool => array_key_exists((int) $actorId, $roles))->each->delete();

        $added = [];

        foreach ($roles as $actorId => $role) {
            $row = $current->get($actorId) ?? $subject->recipients()->make(['actor_id' => $actorId]);

            if (! $row->exists) {
                $added[] = (int) $actorId;
            }

            if (! $row->exists || $row->role !== $role) {
                $row->role = $role;
                $row->save();
            }
        }

        return $added;
    }

    /** @return array<string, mixed> */
    private static function item(Actor $actor, RecipientRole $role): array
    {
        return ['actor' => Payload::actor($actor), 'role' => $role->value, 'support' => Addressables::isGateway($actor)];
    }
}
