<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Events\TaskReceived;
use CharlesStOlive\FilamentTma\Events\TicketSynced;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Notifier;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * L'API du client pour le maître : il pousse l'état d'un ticket du Support, d'une tâche ou d'un projet, puis les
 * fichiers qui manquent. Le maître ne peut pousser qu'un ticket de cette application, et ne joue que l'intervenant.
 *
 * Chaque application notifie ses propres utilisateurs : ce que l'état reçu apporte de nouveau (passages, messages,
 * notes) les prévient (`Support\Notifier`), comme l'avertissement de clôture automatique décidé par le maître.
 */
class SyncController
{
    /** `POST sync` */
    public function sync(Request $request): JsonResponse
    {
        if ($request->input('kind') === 'task') {
            return $this->syncTask($request);
        }

        if ($request->input('kind') === 'project') {
            return $this->syncProject($request);
        }

        $request->validate([
            'ticket' => ['required', 'array'],
            'ticket.ulid' => ['required', 'string', 'size:26'],
            'ticket.source_instance_uid' => ['required', 'in:'.Tma::instanceUid()],
            'entries' => ['array'],
            'transitions' => ['array'],
        ]);

        $before = Ticket::withTrashed()->where('ulid', $request->input('ticket.ulid'))->first();

        // Un ticket inconnu ici ne peut être qu'un ticket préparé par le maître pour nous, et publié.
        $preparedByMaster = ! Tma::isHere($request->input('ticket.created_on_instance_uid')) && $request->input('ticket.state') !== 'draft';
        abort_if($before === null && ! $preparedByMaster, 404, __('Ticket inconnu de cette application.'));

        $warnedBefore = $before?->auto_close_warned_at;

        // Les passages et entrées nouveaux préviennent les utilisateurs d'ici (`SnapshotMirrored`, `Support\Notifier`).
        $result = DB::transaction(fn () => Mirror::snapshot($request->all()));
        $ticket = $result['ticket']->refresh();

        $this->warnRequester($ticket, $warnedBefore);
        event(new TicketSynced($ticket));

        return response()->json(['missing_media' => $result['missing_media']]);
    }

    /**
     * Une tâche projet publiée pour cette application : créée à sa première réception, puis tenue à jour, jusqu'à sa
     * suppression (`deleted_at`). Le maître n'envoie jamais un brouillon.
     */
    private function syncTask(Request $request): JsonResponse
    {
        $request->validate([
            'task' => ['required', 'array'],
            'task.ulid' => ['required', 'string', 'size:26'],
            'task.number' => ['required', 'string'],
            'task.title' => ['required', 'string'],
            'task.target_instance_uid' => ['required', 'in:'.Tma::instanceUid()],
            'task.state' => ['required', 'not_in:draft'],
            'entries' => ['array'],
            'transitions' => ['array'],
        ]);

        $isNew = ! ProjectTask::withTrashed()->where('ulid', $request->input('task.ulid'))->exists();

        // Supprimée dans le maître avant d'arriver ici : rien à recopier.
        if ($isNew && filled($request->input('task.deleted_at'))) {
            return response()->json(['missing_media' => []]);
        }

        $result = DB::transaction(fn () => Mirror::snapshot($request->all()));

        if ($isNew) {
            event(new TaskReceived($result['ticket']));
        }

        return response()->json(['missing_media' => $result['missing_media']]);
    }

    /**
     * Un projet de cette application, seul : créé à sa première réception, même sans tâche publiée, puis tenu à jour,
     * jusqu'à sa suppression (`deleted_at`).
     */
    private function syncProject(Request $request): JsonResponse
    {
        $request->validate([
            'project' => ['required', 'array'],
            'project.ulid' => ['required', 'string', 'size:26'],
            'project.title' => ['required', 'string'],
            'project.target_instance_uid' => ['required', 'in:'.Tma::instanceUid()],
            'project.members' => ['array'],
        ]);

        $known = Project::withTrashed()->where('ulid', $request->input('project.ulid'))->exists();

        // Supprimé dans le maître avant d'arriver ici : rien à recopier.
        if ($known || blank($request->input('project.deleted_at'))) {
            DB::transaction(fn () => Mirror::project($request->input('project'), Tma::instanceUid()));
        }

        return response()->json(['missing_media' => []]);
    }

    /**
     * `POST media/{entry|task|ticket}/{ulid}` : les fichiers d'une entrée, ou les pièces jointes d'une tâche ou d'un
     * ticket, que l'état reçu disait manquer (`missing_media`).
     */
    public function media(Request $request, string $owner, string $ulid): JsonResponse
    {
        $model = match ($owner) {
            'entry' => Entry::query()->where('ulid', $ulid)->firstOrFail(),
            'task' => ProjectTask::query()->where('ulid', $ulid)->firstOrFail(),
            'ticket' => Ticket::query()->where('ulid', $ulid)->firstOrFail(),
            default => abort(404),
        };
        $request->validate(['files' => ['required', 'array']]);

        $subject = $model instanceof Entry ? $model->subject : $model;
        abort_unless($subject instanceof Trackable && Tma::isHere($subject->clientInstanceUid()), 403);

        Mirror::files($model, $request->input('files'));

        return response()->json(['files_count' => $model->refresh()->getMedia(Entry::ATTACHMENTS_COLLECTION)->count()]);
    }

    /**
     * L'avertissement de clôture automatique décidé par le maître, dit au demandeur à sa première réception.
     */
    private function warnRequester(Ticket $ticket, ?\DateTimeInterface $warnedBefore): void
    {
        if ($warnedBefore !== null || $ticket->auto_close_warned_at === null) {
            return;
        }

        $closesOn = $ticket->last_activity_at->copy()->addDays((int) config('filament-tma.auto_close.after_days', 28));
        $closesOn = max($closesOn, $ticket->auto_close_warned_at->copy()->addDays((int) config('filament-tma.auto_close.warn_days_before', 7)));

        Notifier::autoCloseWarning($ticket, $closesOn);
    }
}
