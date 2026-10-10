<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le maître publie un ticket qu'il a préparé pour un client (addendum §3) : invisible jusque-là, il part chez le
 * client, au nom de son demandeur, et attend d'être pris en charge.
 */
class Publish extends Move
{
    public function key(): string
    {
        return 'publish';
    }

    public function label(): string
    {
        return Terms::publish();
    }

    public function labelFor(Ticket $ticket): string
    {
        return Terms::publish($ticket->source_instance_uid);
    }

    public function description(): ?string
    {
        return __('Le ticket devient visible dans l’application du client.');
    }

    public function icon(): ?string
    {
        return 'heroicon-m-megaphone';
    }

    public function from(): array
    {
        return [Draft::class];
    }

    public function to(Ticket $ticket): string
    {
        return Submitted::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function allowsSide(Side $side, Ticket $ticket): bool
    {
        return true;
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return Tma::isMaster() && Tma::isHere($ticket->created_on_instance_uid) && ! Tma::isHere($ticket->source_instance_uid);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->submitted_at = now();
        $ticket->publish_at = null;
    }
}
