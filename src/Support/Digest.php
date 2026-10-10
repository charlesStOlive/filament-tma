<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Ce qui attend un utilisateur, pour le récapitulatif quotidien (CDC §12.1) :
 *
 * - `answer` : ses demandes qui attendent sa réponse ou sa validation ;
 * - `take` : les tickets soumis à cette application, que personne n'a pris et qu'il peut prendre ;
 * - `mine` : ce qu'il traite (tickets en cours, tâches projet en cours qui lui sont confiées) ;
 * - `resume` : ses pauses qui reprennent aujourd'hui.
 *
 * Une attente plus longue que `notifications.digest.late_after_days` jours est signalée en retard.
 */
final class Digest
{
    /** @return array<string, Collection<int, array{ref: string, title: string, state: string, since: ?\DateTimeInterface, late: bool, url: ?string}>> */
    public static function for(Authenticatable $user): array
    {
        $actor = Actor::query()->where('instance_uid', Tma::instanceUid())->where('remote_id', (string) $user->getAuthIdentifier())->first();
        $lateAfter = (int) config('filament-tma.notifications.digest.late_after_days', 5);

        $row = fn (Ticket|ProjectTask $item, ?\DateTimeInterface $since): array => [
            'ref' => $item->number,
            'title' => $item->title,
            'state' => $item->state->getLabel(),
            'since' => $since,
            'late' => $since !== null && now()->diffInDays($since, true) > $lateAfter
                || ($item instanceof ProjectTask && $item->ends_on?->isPast()),
            'url' => rescue(fn () => $item instanceof Ticket ? TicketUrl::view($item) : null, null, false),
        ];

        $answer = $actor === null ? collect() : Ticket::query()
            ->where('requester_actor_id', $actor->getKey())
            ->whereIn('state', ['awaiting_requester', 'pending_validation'])
            ->get()
            ->map(fn (Ticket $t) => $row($t, $t->waiting_since ?? $t->validation_requested_at ?? $t->last_activity_at));

        $take = Ticket::query()->where('state', 'submitted')->whereNull('assignee_actor_id')->get()
            ->filter(fn (Ticket $t): bool => $t->isHandledHere() && TmaGate::allows($user, TmaGate::workflowAction('take-charge'), $t))
            ->map(fn (Ticket $t) => $row($t, $t->submitted_at));

        $mine = $actor === null ? collect() : Ticket::query()
            ->where('assignee_actor_id', $actor->getKey())
            ->whereIn('state', ['submitted', 'in_progress'])
            ->get()
            ->filter(fn (Ticket $t): bool => $t->isHandledHere())
            ->map(fn (Ticket $t) => $row($t, $t->last_activity_at))
            ->concat(Tma::isMaster() ? ProjectTask::query()->where('assignee_actor_id', $actor->getKey())->where('state', 'in_progress')->get()
                ->map(fn (ProjectTask $t) => $row($t, $t->published_at)) : collect());

        $resume = $actor === null ? collect() : Ticket::query()
            ->where('assignee_actor_id', $actor->getKey())
            ->where('state', 'paused')
            ->whereDate('paused_until', now()->toDateString())
            ->get()
            ->map(fn (Ticket $t) => $row($t, $t->paused_until));

        return array_filter(compact('answer', 'take', 'mine', 'resume'), fn (Collection $items): bool => $items->isNotEmpty());
    }
}
