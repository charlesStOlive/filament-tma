<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Forçage (addendum §3.4) : clore un ticket ouvert sans attendre personne — un ticket qui dort, une demande restée
 * sans réponse. Hors du tour, avec un motif ; seul son droit l'ouvre (`tma.ticket.force-close`), des deux côtés.
 */
class ForceClose extends Move
{
    use WritesMessages;

    public function key(): string
    {
        return 'force-close';
    }

    public function label(): string
    {
        return __('Forcer la clôture');
    }

    public function description(): ?string
    {
        return __('Hors du tour : le ticket est clos tout de suite, quel que soit celui qu’on attend.');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-lock-closed';
    }

    public function from(): array
    {
        return [Submitted::class, InProgress::class, Paused::class, AwaitingRequester::class, PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return Closed::class;
    }

    public function sides(): array
    {
        return [Side::Handler, Side::Requester];
    }

    public function override(): ?string
    {
        return TmaGate::FORCE_CLOSE;
    }

    public function rules(Ticket $ticket): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [$this->reasonField(help: ['tma.tickets.forcages', __('Quand forcer la clôture ?')])];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->closed_at = now();
        $ticket->paused_until = null;
    }
}
