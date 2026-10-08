<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Events\TicketSynced;
use CharlesStOlive\FilamentTma\Events\TicketSyncFailed;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Auditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Ce que le journal d'audit retient (CDC §23) : le temps (toute saisie, modification, suppression), les champs des
 * tickets et des tâches, les applications clientes (sans leur jeton), les forfaits, les seuils franchis, et les
 * synchronisations (reçues, en échec). Les changements d'état sont dans `tma_state_transitions`.
 */
final class AuditTrail
{
    public static function register(): void
    {
        foreach ([TimeEntry::class => 'time', Ticket::class => 'ticket', ProjectTask::class => 'task', Instance::class => 'instance', Allowance::class => 'allowance'] as $model => $prefix) {
            $model::created(fn (Model $m) => Auditor::record("{$prefix}.created", $m, null, self::visible($m, $m->getAttributes())));
            $model::updated(function (Model $m) use ($prefix): void {
                [$old, $new] = Auditor::changes($m);

                // Un changement d'état est un mouvement du workflow : lui et ce qu'il écrit (intervenant, dates) sont
                // dans l'historique des transitions.
                if ($new !== [] && ! array_key_exists('state', $new)) {
                    Auditor::record("{$prefix}.updated", $m, $old, $new);
                }
            });
            $model::deleted(fn (Model $m) => Auditor::record("{$prefix}.deleted", $m, self::visible($m, $m->getOriginal()), null));
        }

        Event::listen(TicketSynced::class, fn (TicketSynced $e) => Auditor::record('sync.received', $e->ticket, null, ['version' => $e->ticket->sync_version]));
        Event::listen(TicketSyncFailed::class, fn (TicketSyncFailed $e) => Auditor::record('sync.failed', $e->ticket instanceof Model ? $e->ticket : null, null, null, ['error' => $e->error]));
        Event::listen(AllowanceThresholdReached::class, fn (AllowanceThresholdReached $e) => Auditor::record('allowance.threshold', $e->allowance, null, [
            'threshold' => $e->threshold, 'period' => $e->periodKey, 'consumed_minutes' => $e->status['consumed_minutes'],
        ]));
    }

    /** @param  array<string, mixed>  $values */
    private static function visible(Model $model, array $values): array
    {
        // Les longs textes n'apportent rien à l'audit d'une création ou d'une suppression.
        return collect($values)->except(['description', 'created_at', 'updated_at', 'deleted_at'])->all();
    }
}
