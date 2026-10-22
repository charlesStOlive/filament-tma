<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Uploads;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Ce qu'une application envoie à l'autre (format v1, voir docs/API.md). Les identifiants sont les ulid globaux ;
 * les dates sont en UTC, au format ISO 8601.
 */
final class Payload
{
    /** @return array<string, mixed>|null */
    public static function actor(?Actor $actor): ?array
    {
        return $actor === null ? null : [
            'instance_uid' => $actor->instance_uid,
            'id' => $actor->remote_id,
            'name' => $actor->name,
            'email' => $actor->email,
        ];
    }

    /** @return array<string, mixed> */
    public static function ticket(Ticket $ticket): array
    {
        $ticket->loadMissing(['requester', 'assignee', 'createdBy', 'task']);

        return [
            'ulid' => $ticket->ulid,
            'number' => $ticket->number,
            'source_instance_uid' => $ticket->source_instance_uid,
            'created_on_instance_uid' => $ticket->created_on_instance_uid,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'priority' => $ticket->priority->value,
            'state' => $ticket->state::getMorphClass(),
            'task_ulid' => $ticket->task?->ulid,
            'requester' => self::actor($ticket->requester),
            'assignee' => self::actor($ticket->assignee),
            'created_by' => self::actor($ticket->createdBy),
            'submitted_at' => self::date($ticket->submitted_at),
            'taken_at' => self::date($ticket->taken_at),
            'waiting_since' => self::date($ticket->waiting_since),
            'paused_until' => self::date($ticket->paused_until),
            'validation_requested_at' => self::date($ticket->validation_requested_at),
            'closed_at' => self::date($ticket->closed_at),
            'cancelled_at' => self::date($ticket->cancelled_at),
            'last_activity_at' => self::date($ticket->last_activity_at),
            'auto_close_warned_at' => self::date($ticket->auto_close_warned_at),
            'created_at' => self::date($ticket->created_at),
            'version' => $ticket->sync_version,
            'time' => TimeTotals::for($ticket),
            ...self::fileList($ticket),
        ];
    }

    /**
     * Les pièces jointes d'un ticket ou d'une tâche, sans leur contenu : combien, et lesquelles (`Uploads::uuidOf()`).
     * Celles qui manquent de l'autre côté partent ensuite à part (`Mirror::snapshot()`, `missing_media`).
     *
     * @return array{files_count: int, file_uuids: array<int, string>}
     */
    private static function fileList(HasMedia $subject): array
    {
        $media = $subject->getMedia(Entry::ATTACHMENTS_COLLECTION);

        return [
            'files_count' => $media->count(),
            'file_uuids' => $media->map(fn (Media $file): string => Uploads::uuidOf($file))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function task(ProjectTask $task): array
    {
        $task->loadMissing(['assignee', 'createdBy', 'project.members']);

        return [
            'ulid' => $task->ulid,
            'number' => $task->number,
            'target_instance_uid' => $task->target_instance_uid,
            'project' => $task->project === null ? null : [
                'ulid' => $task->project->ulid,
                'title' => $task->project->title,
                'description' => $task->project->description,
                'estimate_min_days' => $task->project->estimate_min_days,
                'estimate_max_days' => $task->project->estimate_max_days,
                'members' => $task->project->members->map(fn (Actor $actor): array => self::actor($actor))->values()->all(),
            ],
            'title' => $task->title,
            'description' => $task->description,
            'state' => $task->state::getMorphClass(),
            'starts_on' => $task->starts_on?->toDateString(),
            'due_on' => $task->due_on?->toDateString(),
            'assignee' => self::actor($task->assignee),
            'created_by' => self::actor($task->createdBy),
            'published_at' => self::date($task->published_at),
            'done_at' => self::date($task->done_at),
            'cancelled_at' => self::date($task->cancelled_at),
            'last_activity_at' => self::date($task->last_activity_at),
            'created_at' => self::date($task->created_at),
            // Supprimée dans le maître : elle disparaît aussi chez le client.
            'deleted_at' => self::date($task->deleted_at),
            'version' => $task->sync_version,
            'time' => TimeTotals::for($task),
            ...self::fileList($task),
        ];
    }

    /**
     * Une entrée. `deleted_at` dit qu'une note a été supprimée ; `private` qu'elle ne se montre qu'à son auteur ;
     * `file_uuids` liste ses fichiers (ceux qui manquent sont retirés de l'autre côté). `shared_at` n'existe plus que
     * pour les applications d'avant les notes lues de tous, qui n'acceptent une note que « partagée » (jamais une
     * note privée).
     *
     * @return array<string, mixed>
     */
    public static function entry(Entry $entry, bool $withFiles = false): array
    {
        $entry->loadMissing(['author', 'parent', 'subject']);
        $media = $entry->getMedia(Entry::ATTACHMENTS_COLLECTION);

        return [
            'ulid' => $entry->ulid,
            'subject_kind' => $entry->subject?->kind(),
            'ticket_ulid' => $entry->subject?->ulid,
            'parent_ulid' => $entry->parent?->ulid,
            'type' => $entry->type->value,
            'number' => $entry->number,
            'private' => $entry->private,
            'body' => $entry->body,
            'author' => self::actor($entry->author),
            'origin_instance_uid' => $entry->origin_instance_uid,
            'origin' => $entry->origin->value,
            'shared_at' => $entry->isNote() && ! $entry->private ? self::date($entry->created_at) : null,
            'meta' => $entry->meta,
            'created_at' => self::date($entry->created_at),
            'edited_at' => self::date($entry->edited_at),
            'archived_at' => self::date($entry->archived_at),
            'deleted_at' => self::date($entry->deleted_at),
            'files_count' => $media->count(),
            'file_uuids' => $media->map(fn (Media $file): string => Uploads::uuidOf($file))->values()->all(),
            'files' => $withFiles ? self::files($entry) : [],
        ];
    }

    /** @return array<string, mixed> */
    public static function transition(StateTransition $transition): array
    {
        $transition->loadMissing('actor');

        return [
            'ulid' => $transition->ulid,
            'move' => $transition->move,
            'from_state' => $transition->from_state,
            'to_state' => $transition->to_state,
            'actor' => self::actor($transition->actor),
            'origin_instance_uid' => $transition->origin_instance_uid,
            'origin' => $transition->origin->value,
            'reason' => $transition->reason,
            'created_at' => self::date($transition->created_at),
        ];
    }

    /**
     * L'état complet d'un ticket tel que l'autre partie le voit : ses champs, ses entrées, notes supprimées
     * comprises (sans leurs fichiers, envoyés à part s'ils manquent), ses transitions.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Trackable $ticket): array
    {
        return [
            'kind' => $ticket->kind(),
            $ticket->kind() => $ticket->kind() === 'task' ? self::task($ticket) : self::ticket($ticket),
            'entries' => $ticket->entries()->withTrashed()->with(['author', 'parent', 'media'])->oldest('id')->get()
                ->map(fn (Entry $entry): array => self::entry($entry))
                ->values()->all(),
            'transitions' => $ticket->transitions()->with('actor')->oldest('id')->get()
                ->map(fn (StateTransition $transition): array => self::transition($transition))
                ->all(),
            'recipients' => Recipients::payload($ticket),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function files(HasMedia $model): array
    {
        return $model->getMedia(Entry::ATTACHMENTS_COLLECTION)->map(fn (Media $media): array => [
            'uuid' => Uploads::uuidOf($media),
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'content' => base64_encode((string) stream_get_contents($media->stream())),
        ])->values()->all();
    }

    private static function date(?\DateTimeInterface $date): ?string
    {
        return $date === null ? null : Carbon::instance($date)->utc()->toIso8601String();
    }
}
