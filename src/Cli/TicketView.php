<?php

namespace CharlesStOlive\FilamentTma\Cli;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Support\TimelineItems;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Illuminate\Contracts\Auth\Authenticatable;

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
                'waiting_on' => $ticket->state->isFinal() ? null : Terms::support(),
                'application' => $ticket->targetInstance?->name,
                'project' => $ticket->project?->title,
                'assignee' => $ticket->assignee?->name,
                'starts_on' => $ticket->starts_on?->toDateString(),
                'due_on' => $ticket->due_on?->toDateString(),
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
            'waiting_on' => $ticket->waitingOn(),
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
    public static function detail(Ticket|ProjectTask $ticket, ?Authenticatable $user): array
    {
        return [
            ...self::summary($ticket),
            'description' => Html::toText($ticket->description),
            'time_by_month' => TimeTotals::for($ticket)['by_month'],
            'timeline' => TimelineItems::for($ticket)->map(fn (array $row): array => match ($row['kind']) {
                'transition' => self::transition($row['item']),
                default => self::entry($row['item']),
            })->all(),
            'notes' => $ticket->noteEntries($user, withArchived: true)->map(fn (Entry $note): array => self::note($note))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function entry(Entry $entry): array
    {
        return [
            'kind' => $entry->type->value,
            'ulid' => $entry->ulid,
            'at' => $entry->created_at->toIso8601String(),
            'author' => $entry->author?->name,
            'edited_at' => $entry->edited_at?->toIso8601String(),
            'text' => Html::toText($entry->body),
            'files' => $entry->getMedia(Entry::ATTACHMENTS_COLLECTION)->pluck('file_name')->all(),
            'replies' => $entry->replies->map(fn (Entry $reply): array => self::entry($reply))->all(),
        ];
    }

    /** @return array<string, mixed> une note : son numéro, le message qu'elle commente (sinon le ticket entier) */
    private static function note(Entry $note): array
    {
        return [
            'number' => $note->number,
            'on' => $note->parent?->ulid,
            'at' => $note->created_at->toIso8601String(),
            'author' => $note->author?->name,
            'archived' => $note->isArchived(),
            'private' => $note->private,
            'text' => Html::toText($note->body),
            'files' => $note->getMedia(Entry::ATTACHMENTS_COLLECTION)->pluck('file_name')->all(),
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
