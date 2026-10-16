<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\AwaitingRequester;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\PendingValidation;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;

/**
 * Le planificateur clôt un ticket resté sans activité alors que c'était le tour du demandeur
 * (`tma:close-stale`, `filament-tma.auto_close`). Seul le maître le fait : il fait autorité.
 */
class AutoClose extends Move
{
    public function key(): string
    {
        return 'auto-close';
    }

    public function label(): string
    {
        return __('Clos automatiquement (sans réponse)');
    }

    public function from(): array
    {
        return [AwaitingRequester::class, PendingValidation::class];
    }

    public function to(Ticket $ticket): string
    {
        return Closed::class;
    }

    public function sides(): array
    {
        return [];
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return $ticket->authorityIsHere();
    }

    public function reason(array $data): ?string
    {
        return __('Clos automatiquement : aucune activité depuis :days jours.', ['days' => config('filament-tma.auto_close.after_days')]);
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $ticket->closed_at = now();
    }
}
