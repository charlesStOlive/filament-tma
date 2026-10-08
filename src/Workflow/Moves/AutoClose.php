<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Models\Ticket;

/**
 * Le planificateur clôture un ticket resté sans activité alors que la main était au demandeur
 * (`tma:close-stale`, `filament-tma.auto_close`). Seule l'application qui traite le ticket le fait.
 */
class AutoClose extends Close
{
    public function key(): string
    {
        return 'auto-close';
    }

    public function label(): string
    {
        return __('Clôture automatique sans réponse');
    }

    public function sides(): array
    {
        return [];
    }

    public function isAvailable(Ticket $ticket): bool
    {
        return $ticket->isHandledHere();
    }

    public function reason(array $data): ?string
    {
        return __('Clôture automatique : aucune activité depuis :days jours.', ['days' => config('filament-tma.auto_close.after_days')]);
    }
}
