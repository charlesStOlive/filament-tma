<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\TicketAutoClosed;
use CharlesStOlive\FilamentTma\Notifications\TicketMoveNotification;

/**
 * Qui prévenir d'un passage de ticket, ici. Appelé pour un passage fait ici (`TicketTransitioned`) comme pour un
 * passage reçu de l'autre application : chaque application notifie ses propres utilisateurs (addendum §4), et
 * jamais l'auteur du passage.
 */
final class Notifier
{
    public static function transition(Ticket $ticket, StateTransition $transition): void
    {
        if ($transition->move === 'auto-close') {
            self::requester($ticket)?->notify(new TicketAutoClosed($ticket));

            return;
        }

        if (! in_array($transition->move, (array) config('filament-tma.notifications.immediate', []), true)) {
            return;
        }

        $recipient = match ($transition->move) {
            'request-answer', 'submit-for-validation', 'take-charge' => self::requester($ticket),
            'submit', 'reopen', 'answer' => self::localUser($ticket->assignee),
            default => null,
        };

        if ($recipient !== null && ! ($transition->actor?->isUser($recipient))) {
            $recipient->notify(new TicketMoveNotification($ticket, $transition));
        }
    }

    private static function requester(Ticket $ticket): ?object
    {
        return self::localUser($ticket->requester);
    }

    private static function localUser(?Actor $actor): ?object
    {
        return $actor?->isLocal() ? $actor->user : null;
    }
}
