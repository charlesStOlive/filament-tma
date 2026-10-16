<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Cancelled;
use CharlesStOlive\FilamentTma\States\Ticket\Closed;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ce qui ne bouge plus (`notifications.stale.after_days`, 7 jours par défaut), rappelé à qui a la main
 * (`Notifier::stale()`) :
 * - un ticket qui n'a pas changé d'état depuis, ni en brouillon, ni en pause, ni terminé ;
 * - dans le maître, une tâche en cours sans activité depuis (aucun message, note ou temps) : une tâche ne change
 *   d'état qu'à sa publication et à sa fin.
 *
 * Chacun ne prévient que ses propres utilisateurs : le maître son intervenant, le client son demandeur.
 */
final class StaleReminders
{
    /**
     * @param  \Closure(Trackable, int, int): void|null  $report  appelé pour chaque élément rappelé (jours, personnes)
     * @return array{subjects: int, reminders: int}
     */
    public static function run(bool $dryRun = false, ?\Closure $report = null): array
    {
        $limit = now()->subDays((int) config('filament-tma.notifications.stale.after_days', 7));
        $subjects = 0;
        $reminders = 0;

        $remind = function (Trackable $subject, mixed $since) use ($dryRun, $report, &$subjects, &$reminders): void {
            $days = (int) now()->diffInDays(Carbon::parse($since), true);
            $sent = Notifier::stale($subject, $days, $dryRun);

            if ($sent > 0) {
                $subjects++;
                $reminders += $sent;
                $report?->__invoke($subject, $days, $sent);
            }
        };

        Ticket::query()
            ->whereNotIn('state', array_map(fn (string $state): string => $state::getMorphClass(), [Draft::class, Paused::class, Closed::class, Cancelled::class]))
            ->where('created_at', '<', $limit)
            ->whereDoesntHave('transitions', fn (Builder $q): Builder => $q->where('created_at', '>=', $limit))
            ->with(['requester', 'assignee'])
            ->each(fn (Ticket $ticket) => $remind($ticket, $ticket->transitions()->max('created_at') ?? $ticket->created_at));

        if (Tma::isMaster()) {
            ProjectTask::query()
                ->where('state', TaskInProgress::getMorphClass())
                ->whereNotNull('assignee_actor_id')
                ->where(fn (Builder $q): Builder => $q->where('last_activity_at', '<', $limit)
                    ->orWhere(fn (Builder $q): Builder => $q->whereNull('last_activity_at')->where('created_at', '<', $limit)))
                ->with('assignee')
                ->each(fn (ProjectTask $task) => $remind($task, $task->last_activity_at ?? $task->created_at));
        }

        return ['subjects' => $subjects, 'reminders' => $reminders];
    }
}
