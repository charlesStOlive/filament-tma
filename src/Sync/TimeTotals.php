<?php

namespace CharlesStOlive\FilamentTma\Sync;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Support\Tma;

/**
 * Les totaux de temps d'un ticket que l'autre partie peut voir : le total et le détail par mois, en minutes.
 * Les lignes de temps restent dans le maître (addendum §7). Côté client, ce sont les totaux reçus.
 */
final class TimeTotals
{
    /** @return array{total_minutes: int, by_month: array<string, int>} */
    public static function for(Trackable $ticket): array
    {
        if (! Tma::isMaster()) {
            return ['total_minutes' => (int) $ticket->time_total_minutes, 'by_month' => (array) ($ticket->time_by_month ?? [])];
        }

        $byMonth = $ticket->timeEntries()
            ->get(['worked_on', 'minutes'])
            ->groupBy(fn ($entry): string => $entry->worked_on->format('Y-m'))
            ->map(fn ($entries): int => (int) $entries->sum('minutes'))
            ->sortKeys()
            ->all();

        return ['total_minutes' => array_sum($byMonth), 'by_month' => $byMonth];
    }
}
