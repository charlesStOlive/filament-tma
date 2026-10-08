<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * La chronologie affichée : les entrées visibles ici (avec leurs réponses) et les changements d'état, par date.
 */
final class TimelineItems
{
    /** @return Collection<int, array{kind: string, at: \Illuminate\Support\Carbon, item: Entry|StateTransition}> */
    public static function for(Ticket $ticket, bool $withNotes): Collection
    {
        $entries = $ticket->timelineEntries($withNotes)
            ->map(fn (Entry $entry): array => ['kind' => 'entry', 'at' => $entry->created_at, 'item' => $entry]);

        $transitions = $ticket->transitions()->with('actor')->get()
            ->map(fn (StateTransition $transition): array => ['kind' => 'transition', 'at' => $transition->created_at, 'item' => $transition]);

        return $entries->concat($transitions)
            ->sortBy(fn (array $row): string => $row['at']->format('Y-m-d H:i:s.u').($row['kind'] === 'transition' ? '0' : '1'))
            ->values();
    }
}
