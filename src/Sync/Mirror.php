<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

/**
 * Recopie ici ce que l'autre application a envoyé (format de `Payload`). Tout est indexé par les ulid globaux :
 * recevoir deux fois la même chose ne crée rien de plus (CDC §15.6). La recopie ne relance pas le workflow : elle
 * reproduit le résultat obtenu là où il s'est fait.
 */
final class Mirror
{
    /** @param  array<string, mixed>|null  $payload */
    public static function actor(?array $payload): ?Actor
    {
        if ($payload === null) {
            return null;
        }

        $here = Tma::isHere($payload['instance_uid'] ?? null);

        return Actor::fromInstance(
            (string) $payload['instance_uid'],
            (string) $payload['id'],
            (string) ($payload['name'] ?? $payload['email'] ?? $payload['id']),
            $payload['email'] ?? null,
            $here ? $payload['id'] : null,
        );
    }

    /**
     * Les champs d'un ticket. Une version plus ancienne que celle déjà reçue ne remplace pas les champs.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $overrides  ce que l'application qui reçoit décide elle-même (mode, client…)
     */
    public static function ticket(array $payload, array $overrides = []): Ticket
    {
        $ticket = Ticket::withTrashed()->firstOrNew(['ulid' => $payload['ulid']]);

        if ($ticket->exists && (int) ($payload['version'] ?? 0) < (int) $ticket->sync_version) {
            return $ticket;
        }

        $time = $payload['time'] ?? null;

        $ticket->forceFill([
            ...Arr::only($payload, [
                'number', 'source_instance_uid', 'created_on_instance_uid', 'title', 'description', 'priority',
                'state', 'handling_mode', 'due_on',
            ]),
            ...self::dates($payload, [
                'submitted_at', 'taken_at', 'waiting_since', 'paused_until', 'validation_requested_at', 'closed_at',
                'cancelled_at', 'last_activity_at', 'auto_close_warned_at',
            ]),
            'requester_actor_id' => self::actor($payload['requester'])?->getKey(),
            'assignee_actor_id' => self::actor($payload['assignee'] ?? null)?->getKey(),
            'created_by_actor_id' => self::actor($payload['created_by'] ?? $payload['requester'])?->getKey(),
            'sync_version' => (int) ($payload['version'] ?? 0),
            'sync_status' => SyncStatus::Synced,
            'synced_at' => now(),
            'last_sync_error' => null,
            ...(! Tma::isMaster() && is_array($time) ? [
                'time_total_minutes' => (int) ($time['total_minutes'] ?? 0),
                'time_by_month' => $time['by_month'] ?? [],
            ] : []),
            ...$overrides,
        ]);

        if (! $ticket->exists && isset($payload['created_at'])) {
            $ticket->created_at = Carbon::parse($payload['created_at']);
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Une tâche projet, reçue du maître (le seul à en créer). Une version plus ancienne ne remplace pas les champs.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $overrides
     */
    public static function task(array $payload, array $overrides = []): \CharlesStOlive\FilamentTma\Models\ProjectTask
    {
        $task = \CharlesStOlive\FilamentTma\Models\ProjectTask::withTrashed()->firstOrNew(['ulid' => $payload['ulid']]);

        if ($task->exists && (int) ($payload['version'] ?? 0) < (int) $task->sync_version) {
            return $task;
        }

        $time = $payload['time'] ?? null;

        $task->forceFill([
            ...Arr::only($payload, ['number', 'target_instance_uid', 'project', 'title', 'description', 'state', 'starts_on', 'ends_on']),
            ...self::dates($payload, ['published_at', 'done_at', 'cancelled_at', 'last_activity_at']),
            'assignee_actor_id' => self::actor($payload['assignee'] ?? null)?->getKey(),
            'created_by_actor_id' => self::actor($payload['created_by'] ?? null)?->getKey(),
            'sync_version' => (int) ($payload['version'] ?? 0),
            'sync_status' => SyncStatus::Synced,
            'synced_at' => now(),
            'last_sync_error' => null,
            ...(! Tma::isMaster() && is_array($time) ? [
                'time_total_minutes' => (int) ($time['total_minutes'] ?? 0),
                'time_by_month' => $time['by_month'] ?? [],
            ] : []),
            ...$overrides,
        ]);

        if (! $task->exists && isset($payload['created_at'])) {
            $task->created_at = Carbon::parse($payload['created_at']);
        }

        $task->save();

        return $task;
    }

    /**
     * Une entrée de la chronologie. Une note n'arrive que partagée : une note interne de l'autre application ne
     * doit jamais être reçue.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function entry(Trackable $ticket, array $payload): Entry
    {
        $type = EntryType::from($payload['type']);

        if ($type === EntryType::Note && empty($payload['shared_at'])) {
            throw ValidationException::withMessages(['type' => __('Une note interne ne se transmet pas.')]);
        }

        $entry = Entry::withTrashed()->firstOrNew(['ulid' => $payload['ulid']]);

        if ($entry->exists && ($entry->subject_type !== $ticket->getMorphClass() || (int) $entry->subject_id !== (int) $ticket->getKey())) {
            throw ValidationException::withMessages(['ulid' => __('Cette entrée appartient à un autre ticket.')]);
        }

        $parent = filled($payload['parent_ulid'] ?? null)
            ? $ticket->entries()->where('ulid', $payload['parent_ulid'])->whereNull('parent_id')->first()
            : null;

        $entry->forceFill([
            'subject_type' => $ticket->getMorphClass(),
            'subject_id' => $ticket->getKey(),
            'parent_id' => $parent?->getKey(),
            'type' => $type,
            'body' => (string) $payload['body'],
            'author_actor_id' => self::actor($payload['author'])->getKey(),
            'origin_instance_uid' => $payload['origin_instance_uid'],
            'origin' => $payload['origin'] ?? 'api',
            'shared_at' => self::date($payload['shared_at'] ?? null),
            'meta' => $payload['meta'] ?? null,
        ]);

        if (! $entry->exists && isset($payload['created_at'])) {
            $entry->created_at = Carbon::parse($payload['created_at']);
        }

        $entry->save();

        if (! empty($payload['files'])) {
            self::files($entry, $payload['files']);
        }

        return $entry;
    }

    /** @param  array<string, mixed>  $payload */
    public static function transition(Trackable $ticket, array $payload): StateTransition
    {
        $transition = StateTransition::query()->firstOrNew(['ulid' => $payload['ulid']]);

        if (! $transition->exists) {
            $transition->forceFill([
                'subject_type' => $ticket->getMorphClass(),
                'subject_id' => $ticket->getKey(),
                'move' => $payload['move'],
                'from_state' => $payload['from_state'] ?? null,
                'to_state' => $payload['to_state'],
                'actor_id' => self::actor($payload['actor'] ?? null)?->getKey(),
                'origin_instance_uid' => $payload['origin_instance_uid'],
                'origin' => $payload['origin'] ?? 'api',
                'reason' => $payload['reason'] ?? null,
                'created_at' => isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now(),
            ])->save();
        }

        return $transition;
    }

    /**
     * Un état complet (`Payload::snapshot()`) : le ticket, ses transitions, ses entrées.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $overrides
     * @return array{ticket: Trackable, missing_media: array<int, string>}
     */
    public static function snapshot(array $snapshot, array $overrides = []): array
    {
        $ticket = ($snapshot['kind'] ?? 'ticket') === 'task'
            ? self::task($snapshot['task'], $overrides)
            : self::ticket($snapshot['ticket'], $overrides);

        foreach ($snapshot['transitions'] ?? [] as $transition) {
            self::transition($ticket, $transition);
        }

        // Les réponses après leur message : le parent doit exister.
        $entries = collect($snapshot['entries'] ?? [])->sortBy(fn (array $entry): int => filled($entry['parent_ulid'] ?? null) ? 1 : 0);
        $missing = [];

        foreach ($entries as $payload) {
            $entry = self::entry($ticket, $payload);

            if (($payload['files_count'] ?? 0) > $entry->getMedia(Entry::ATTACHMENTS_COLLECTION)->count() && empty($payload['files'])) {
                $missing[] = $entry->ulid;
            }
        }

        return ['ticket' => $ticket, 'missing_media' => $missing];
    }

    /**
     * Les fichiers reçus (en base64), rangés une seule fois chacun (`remote_uuid`). Ils suivent les règles de la
     * collection : un type hors de la liste blanche est refusé.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    public static function files(HasMedia $model, array $files): void
    {
        $known = $model->getMedia(Entry::ATTACHMENTS_COLLECTION)->map(fn ($media) => $media->getCustomProperty('remote_uuid', $media->uuid))->all();
        $maxBytes = (int) config('filament-tma.attachments.max_size_kb', 10240) * 1024;

        foreach ($files as $file) {
            if (in_array($file['uuid'] ?? null, $known, true)) {
                continue;
            }

            $content = base64_decode((string) ($file['content'] ?? ''), true);

            if ($content === false || strlen($content) > $maxBytes) {
                throw ValidationException::withMessages(['files' => __('Fichier illisible ou trop gros : :name', ['name' => $file['file_name'] ?? '?'])]);
            }

            $model->addMediaFromString($content)
                ->usingName((string) ($file['name'] ?? 'fichier'))
                ->usingFileName(basename((string) ($file['file_name'] ?? 'fichier')))
                ->withCustomProperties(['remote_uuid' => $file['uuid'] ?? null])
                ->toMediaCollection(Entry::ATTACHMENTS_COLLECTION);
        }
    }

    /** @return array<string, Carbon|null> */
    private static function dates(array $payload, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => self::date($payload[$key] ?? null)])->all();
    }

    private static function date(?string $value): ?Carbon
    {
        return $value === null || $value === '' ? null : Carbon::parse($value);
    }
}
