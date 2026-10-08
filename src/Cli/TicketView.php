<?php

namespace CharlesStOlive\FilamentTma\Cli;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Support\TimelineItems;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;

/** Un ticket en tableau simple, pour une commande (texte ou JSON). Le HTML devient du texte. */
final class TicketView
{
    /** @return array<string, mixed> */
    public static function summary(Ticket|ProjectTask $ticket): array
    {
        if ($ticket instanceof ProjectTask) {
            return [
                'kind' => 'task',
                'ref' => $ticket->number,
                'ulid' => $ticket->ulid,
                'title' => $ticket->title,
                'state' => $ticket->state::getMorphClass(),
                'state_label' => $ticket->state->getLabel(),
                'in_hands_of' => $ticket->state->isFinal() ? null : 'handler',
                'application' => $ticket->targetInstance?->name,
                'project' => $ticket->project,
                'assignee' => $ticket->assignee?->name,
                'ends_on' => $ticket->ends_on?->toDateString(),
                'time_minutes' => TimeTotals::for($ticket)['total_minutes'],
                'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            ];
        }

        return [
            'kind' => 'ticket',
            'ref' => $ticket->number,
            'ulid' => $ticket->ulid,
            'title' => $ticket->title,
            'state' => $ticket->state::getMorphClass(),
            'state_label' => $ticket->state->getLabel(),
            'in_hands_of' => $ticket->sideInCharge()?->value,
            'handling_mode' => $ticket->handling_mode->value,
            'application' => $ticket->sourceInstance?->name,
            'priority' => $ticket->priority->value,
            'requester' => $ticket->requester?->name,
            'assignee' => $ticket->assignee?->name,
            'time_minutes' => TimeTotals::for($ticket)['total_minutes'],
            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            'url' => rescue(fn () => TicketUrl::view($ticket), null, false),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Ticket|ProjectTask $ticket, bool $withNotes): array
    {
        return [
            ...self::summary($ticket),
            'description' => Html::toText($ticket->description),
            'due_on' => $ticket instanceof Ticket ? $ticket->due_on?->toDateString() : $ticket->ends_on?->toDateString(),
            'time_by_month' => TimeTotals::for($ticket)['by_month'],
            'timeline' => TimelineItems::for($ticket, $withNotes)->map(fn (array $row): array => match ($row['kind']) {
                'transition' => self::transition($row['item']),
                default => self::entry($row['item']),
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function entry(Entry $entry): array
    {
        return [
            'kind' => $entry->type->value,
            'at' => $entry->created_at->toIso8601String(),
            'author' => $entry->author?->name,
            'shared' => $entry->isShared(),
            'text' => Html::toText($entry->body),
            'files' => $entry->getMedia(Entry::ATTACHMENTS_COLLECTION)->pluck('file_name')->all(),
            'replies' => $entry->replies->map(fn (Entry $reply): array => self::entry($reply))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function transition(StateTransition $transition): array
    {
        return [
            'kind' => 'transition',
            'at' => $transition->created_at->toIso8601String(),
            'author' => $transition->actor?->name,
            'move' => $transition->move,
            'from' => $transition->from_state,
            'to' => $transition->to_state,
            'reason' => $transition->reason,
        ];
    }
}
