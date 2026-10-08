<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Events\TicketSynced;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\AutoCloseWarning;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * L'API du client pour le maître : il pousse l'état d'un ticket du centre de service, puis les fichiers qui
 * manquent. Le maître ne peut pousser qu'un ticket de cette application, et ne joue que l'intervenant.
 *
 * Chaque application notifie ses propres utilisateurs : c'est ici que le demandeur apprend l'avertissement ou la
 * clôture automatique décidés par le maître.
 */
class SyncController
{
    /** `POST sync` */
    public function sync(Request $request): JsonResponse
    {
        if ($request->input('kind') === 'task') {
            return $this->syncTask($request);
        }

        $request->validate([
            'ticket' => ['required', 'array'],
            'ticket.ulid' => ['required', 'string', 'size:26'],
            'ticket.source_instance_uid' => ['required', 'in:'.Tma::instanceUid()],
            'ticket.handling_mode' => ['required', 'in:'.HandlingMode::ServiceDesk->value],
            'entries' => ['array'],
            'transitions' => ['array'],
        ]);

        $before = Ticket::withTrashed()->where('ulid', $request->input('ticket.ulid'))->first();
        abort_if($before === null, 404, __('Ticket inconnu de cette application.'));

        $warnedBefore = $before->auto_close_warned_at;
        $knownTransitions = $before->transitions()->pluck('ulid')->all();

        $result = DB::transaction(fn () => Mirror::snapshot($request->all()));
        $ticket = $result['ticket']->refresh();

        $this->notifyRequester($ticket, $warnedBefore, $knownTransitions);
        event(new TicketSynced($ticket));

        return response()->json(['missing_media' => $result['missing_media']]);
    }

    /**
     * Une tâche projet publiée pour cette application : créée à sa première réception, puis tenue à jour. Le maître
     * n'envoie jamais un brouillon.
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

        $isNew = ! \CharlesStOlive\FilamentTma\Models\ProjectTask::withTrashed()->where('ulid', $request->input('task.ulid'))->exists();
        $result = DB::transaction(fn () => Mirror::snapshot($request->all()));

        if ($isNew) {
            event(new \CharlesStOlive\FilamentTma\Events\TaskReceived($result['ticket']));
        }

        return response()->json(['missing_media' => $result['missing_media']]);
    }

    /** `POST media/entry/{ulid}` */
    public function media(Request $request, string $owner, string $ulid): JsonResponse
    {
        abort_unless($owner === 'entry', 404);
        $request->validate(['files' => ['required', 'array']]);

        $entry = Entry::query()->where('ulid', $ulid)->firstOrFail();
        abort_unless($entry->subject instanceof \CharlesStOlive\FilamentTma\Contracts\Trackable && Tma::isHere($entry->subject->clientInstanceUid()), 403);

        Mirror::files($entry, $request->input('files'));

        return response()->json(['files_count' => $entry->refresh()->getMedia(Entry::ATTACHMENTS_COLLECTION)->count()]);
    }

    /** @param  array<int, string>  $knownTransitions */
    private function notifyRequester(Ticket $ticket, ?\DateTimeInterface $warnedBefore, array $knownTransitions): void
    {
        $user = $ticket->requester?->isLocal() ? $ticket->requester->user : null;

        if ($user === null) {
            return;
        }

        if ($warnedBefore === null && $ticket->auto_close_warned_at !== null) {
            $closesOn = $ticket->last_activity_at->copy()->addDays((int) config('filament-tma.auto_close.after_days', 28));
            $user->notify(new AutoCloseWarning($ticket, max($closesOn, $ticket->auto_close_warned_at->copy()->addDays((int) config('filament-tma.auto_close.warn_days_before', 7)))));
        }

        $closedNow = $ticket->transitions()->where('move', 'auto-close')->whereNotIn('ulid', $knownTransitions)->exists();

        if ($closedNow) {
            $user->notify(new TicketAutoClosed($ticket));
        }
    }
}
