<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Master;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateInstance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Les tâches projet, pour l'application cliente à laquelle elles sont destinées : leur état, et ce qu'elle y ajoute
 * (commentaires, réponses, notes partagées, images). Une tâche en brouillon n'existe pas pour elle.
 */
class TaskController
{
    /** `GET tasks/{ulid}` */
    public function show(Request $request, string $ulid): JsonResponse
    {
        return response()->json(Payload::snapshot($this->task($request, $ulid)));
    }

    /** `POST tasks/{ulid}/entries` */
    public function entry(Request $request, string $ulid): JsonResponse
    {
        $instance = AuthenticateInstance::instance($request);
        $task = $this->task($request, $ulid);

        $payload = $request->validate([
            'ulid' => ['required', 'string', 'size:26'],
            'type' => ['required', 'in:'.EntryType::Comment->value.','.EntryType::Note->value],
            'body' => ['required', 'string'],
            'author' => ['required', 'array'],
            'author.instance_uid' => ['required', 'in:'.$instance->uid],
            'origin_instance_uid' => ['required', 'in:'.$instance->uid],
            'files' => ['array'],
        ]);

        abort_if($task->state->isFinal() && $payload['type'] !== EntryType::Note->value, 422, __('Cette tâche est terminée.'));

        $isNew = ! $task->entries()->where('ulid', $payload['ulid'])->exists();
        $entry = DB::transaction(fn () => Mirror::entry($task, $request->all()));

        if ($isNew) {
            $task->touchActivity();
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
