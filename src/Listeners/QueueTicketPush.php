<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Events\AutoCloseWarned;
use CharlesStOlive\FilamentTma\Events\EntryAdded;
use CharlesStOlive\FilamentTma\Events\NoteShared;
use CharlesStOlive\FilamentTma\Events\TicketTransitioned;
use CharlesStOlive\FilamentTma\Events\TicketUpdated;
use CharlesStOlive\FilamentTma\Jobs\PushTicketToInstance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Tma;

/**
 * Côté maître : tout changement d'un ticket du centre de service part vers son application cliente. Ce qui vient
 * d'elle par l'API n'y retourne pas : elle a déjà recopié la réponse.
 */
class QueueTicketPush
{
    public function handle(TicketTransitioned|EntryAdded|NoteShared|TicketUpdated|AutoCloseWarned $event): void
    {
        $ticket = match (true) {
            $event instanceof EntryAdded => $event->entry->subject,
            $event instanceof NoteShared => $event->note->subject,
            default => $event->ticket,
        };

        $context = $event->context ?? null;

        if (! $ticket instanceof Ticket || ! Tma::isMaster() || $ticket->handling_mode !== HandlingMode::ServiceDesk) {
            return;
        }

        if ($event instanceof EntryAdded && ! $event->entry->isShared()) {
            return; // une note interne du maître ne part pas
        }

        if ($context?->isApi() && $context->instanceUid === $ticket->source_instance_uid) {
            return;
        }

        if (! Instance::query()->active()->where('uid', $ticket->source_instance_uid)->exists()) {
            return;
        }

        $ticket->forceFill(['sync_status' => SyncStatus::Pending, 'sync_version' => $ticket->sync_version + 1])->saveQuietly();

        // Avec une file synchrone, un échec ne doit pas faire échouer l'action de l'utilisateur : le ticket passe
        // simplement « en erreur » (voir `PushTicketToInstance::failed()`), et se relance.
        rescue(function () use ($ticket): void {
            PushTicketToInstance::dispatch($ticket);
        });
    }
}
