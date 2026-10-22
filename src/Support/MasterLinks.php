<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\ProjectTask;

/**
 * Client : les liens vers le panneau du maître, pour un membre du Support relié ici (`Agent`). Il ne fait pas de
 * demande ici (il n'est jamais demandeur) : « Nouvelle demande » l'emmène préparer le ticket chez le Support, pour un
 * correspondant de cette application.
 *
 * L'adresse du panneau : `master.panel_url`, sinon l'adresse du maître suivie de `/admin`.
 */
final class MasterLinks
{
    /** Le panneau du maître (« https://filbreeze.fr/admin ») ; null sans maître configuré. */
    public static function panelUrl(): ?string
    {
        $panel = config('filament-tma.master.panel_url');

        if (filled($panel)) {
            return rtrim((string) $panel, '/');
        }

        $master = config('filament-tma.master.url');

        return filled($master) ? rtrim((string) $master, '/').'/admin' : null;
    }

    /** La personne connectée est un membre du Support relié ici, et le maître est joignable. */
    public static function forAgent(): bool
    {
        return Tma::isClient() && Agent::for(auth()->user()) !== null && self::panelUrl() !== null;
    }

    /** Préparer un ticket chez le Support pour cette application ; depuis une tâche, lié à elle. */
    public static function createTicket(?ProjectTask $task = null): ?string
    {
        $panel = self::panelUrl();

        if ($panel === null) {
            return null;
        }

        return $panel.'/tma/tickets/create?'.http_build_query($task !== null ? ['task' => $task->ulid] : ['instance' => Tma::instanceUid()]);
    }
}
