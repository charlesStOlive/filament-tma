<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Events\TaskTransitioned;
use CharlesStOlive\FilamentTma\Events\TaskUpdated;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Jobs\PushToInstance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Tma;

/**
 * Côté maître : tout changement d'un ticket du centre de service, ou d'une tâche publiée, part vers son application
 * cliente. Ce qui vient
 * d'elle par l'API n'y retourne pas : elle a déjà recopié la réponse.
 */
class QueuePush
{
    public function handle(TicketTransitioned|TaskTransitioned|EntryAdded|NoteShared|TicketUpdated|TaskUpdated|AutoCloseWarned|TimeEntryChanged $event): void
    {
        $ticket = match (true) {
            $event instanceof TaskTransitioned, $event instanceof TaskUpdated => $event->task,
            $event instanceof EntryAdded => $event->entry->subject,
            $event instanceof NoteShared => $event->note->subject,
            $event instanceof TimeEntryChanged => $event->entry->subject,
            default => $event->ticket,
        };

        $context = $event->context ?? null;

        if (! $ticket instanceof Trackable || ! Tma::isMaster() || ! $ticket->isShared()) {
            return;
        }

        if ($event instanceof EntryAdded && ! $event->entry->isShared()) {
            return; // une note interne du maître ne part pas
        }

        if ($context?->isApi() && $context->instanceUid === $ticket->clientInstanceUid()) {
            return;
        }

        if (! Instance::query()->active()->where('uid', $ticket->clientInstanceUid())->exists()) {
            return;
        }

        $ticket->forceFill(['sync_status' => SyncStatus::Pending, 'sync_version' => $ticket->sync_version + 1])->saveQuietly();

        // Avec une file synchrone, un échec ne doit pas faire échouer l'action de l'utilisateur : le ticket passe
        // simplement « en erreur » (voir `PushToInstance::failed()`), et se relance.
        rescue(function () use ($ticket): void {
            PushToInstance::dispatch($ticket);
        });
    }
}
