<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\RecipientsChanged;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateInstance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Les tâches projet, pour l'application cliente à laquelle elles sont destinées : leur état, et ce qu'elle y ajoute
 * (messages, réponses, notes, images). Une tâche en brouillon n'existe pas pour elle.
 */
class TaskController
{
    /** `GET tasks/{ulid}` */
    public function show(Request $request, string $ulid): JsonResponse
    {
        return response()->json(Payload::snapshot($this->task($request, $ulid)));
    }

    /** `POST tasks/{ulid}/recipients` : comme pour un ticket (`TicketController::recipients()`). */
    public function recipients(Request $request, string $ulid): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $task = $this->task($request, $ulid);
        $request->validate(['recipients' => ['present', 'array'], 'actor' => ['required', 'array'], 'actor.instance_uid' => ['in:'.$instance->uid]]);

        $actor = Mirror::actor($request->input('actor'));
        $added = app(Recipients::class)->fromClient($task, $instance->uid, $request->input('recipients', []));
        Notifier::recipientsAdded($task, $added, $actor);
        event(new RecipientsChanged($task, Context::api($actor, $instance->uid, fromMaster: false)));

        return response()->json(Payload::snapshot($task->refresh()));
    }

    /** `POST tasks/{ulid}/entries` : comme pour un ticket (`TicketController::entry()`). */
    public function entry(Request $request, string $ulid): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $task = $this->task($request, $ulid);

        // Une nouvelle entrée est de l'application appelante ; renvoyée, une entrée garde son auteur et son origine.
        $isNew = ! $task->entries()->withTrashed()->where('ulid', (string) $request->input('ulid'))->exists();
        $payload = $request->validate([
            'ulid' => ['required', 'string', 'size:26'],
            'type' => ['required', 'in:'.EntryType::Comment->value.','.EntryType::Note->value],
            'body' => ['nullable', 'string'],
            'author' => [$isNew ? 'required' : 'nullable', 'array'],
            'author.instance_uid' => $isNew ? ['required', 'in:'.$instance->uid] : ['nullable'],
            'origin_instance_uid' => $isNew ? ['required', 'in:'.$instance->uid] : ['nullable'],
            'files' => ['array'],
            'file_uuids' => ['array'],
            'private' => ['boolean'],
        ]);

        abort_if($isNew && ! $task->acceptsMessages() && $payload['type'] !== EntryType::Note->value, 422, __('filament-tma::sync.errors.task_cancelled'));

        $entry = DB::transaction(fn () => Mirror::entryFrom($instance->uid, $task, $request->all()));

        if ($isNew) {
            if (! $entry->private) {
                $task->touchActivity();
            }

            event(new EntryAdded($entry, Context::api(Mirror::actor($request->input('author')), $instance->uid, fromMaster: false)));
        }

        return response()->json(['entry' => Payload::entry($entry)], $isNew ? 201 : 200);
    }

    private function task(Request $request, string $ulid): ProjectTask
    {
        return ProjectTask::query()
            ->where('ulid', $ulid)
            ->where('target_instance_uid', AuthenticateInstance::instance($request)->uid)
            ->where('state', '!=', 'draft')
            ->firstOrFail();
    }
}
