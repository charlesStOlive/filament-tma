<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Models\AllowanceAlert;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La consommation d'un forfait (CDC §11.2) : le temps facturable des tickets des applications couvertes, saisi
 * dans la période (et celui des tâches projet si `allowances.consume` les comprend). Puis les seuils.
 */
final class AllowanceMeter
{
    /** La clé de la période en cours, ou de celle qui contient un jour donné. */
    public static function periodKey(Allowance $allowance, ?\DateTimeInterface $day = null): string
    {
        return $allowance->period->keyFor($day ?? now());
    }

    public static function consumed(Allowance $allowance, string $periodKey): int
    {
        [$from, $to] = $allowance->period->bounds($periodKey);

        return (int) self::entries($allowance)->whereBetween('worked_on', [$from->toDateString(), $to->toDateString()])->sum('minutes');
    }

    /**
     * Tout ce qu'il faut afficher pour une période : alloué, consommé, restant, pourcentage, dépassement, et la
     * répartition par mois.
     *
     * @return array<string, mixed>
     */
    public static function status(Allowance $allowance, ?string $periodKey = null): array
    {
        $periodKey ??= self::periodKey($allowance);
        [$from, $to] = $allowance->period->bounds($periodKey);
        $consumed = self::consumed($allowance, $periodKey);

        $byMonth = self::entries($allowance)
            ->whereBetween('worked_on', [$from->toDateString(), $to->toDateString()])
            ->get(['worked_on', 'minutes'])
            ->groupBy(fn (TimeEntry $entry): string => $entry->worked_on->format('Y-m'))
            ->map(fn (Collection $entries): int => (int) $entries->sum('minutes'))
            ->sortKeys()
            ->all();

        return [
            'allowance_ulid' => $allowance->ulid,
            'label' => $allowance->label(),
            'contract' => $allowance->contract,
            'period' => $allowance->period->value,
            'period_key' => $periodKey,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'allocated_minutes' => $allowance->minutes,
            'consumed_minutes' => $consumed,
            'remaining_minutes' => max(0, $allowance->minutes - $consumed),
            'overage_minutes' => max(0, $consumed - $allowance->minutes),
            'percent' => $allowance->minutes > 0 ? round($consumed * 100 / $allowance->minutes, 1) : null,
            'by_month' => $byMonth,
            'instances' => $allowance->instanceUids(),
        ];
    }

    /**
     * Les seuils franchis pour la période et pas encore retenus : on les retient et on lance l'événement, une fois.
     *
     * @return array<int, int> les seuils franchis maintenant
     */
    public static function checkThresholds(Allowance $allowance, string $periodKey): array
    {
        if ($allowance->minutes <= 0) {
            return [];
        }

        $consumed = self::consumed($allowance, $periodKey);
        $percent = $consumed * 100 / $allowance->minutes;
        $known = $allowance->alerts()->where('period_key', $periodKey)->pluck('threshold')->all();
        $reached = [];

        foreach ((array) config('filament-tma.allowances.thresholds', [80, 90, 100, 110]) as $threshold) {
            if ($percent >= $threshold && ! in_array((int) $threshold, $known, true)) {
                AllowanceAlert::create([
                    'allowance_id' => $allowance->getKey(),
                    'period_key' => $periodKey,
                    'threshold' => (int) $threshold,
                    'consumed_minutes' => $consumed,
                    'reached_at' => now(),
                ]);
                $reached[] = (int) $threshold;
            }
        }

        foreach ($reached as $threshold) {
            event(new AllowanceThresholdReached($allowance, $periodKey, $threshold, self::status($allowance, $periodKey)));
        }

        return $reached;
    }

    /** Réinitialisation explicite des seuils d'une période (CDC §11.3) : ils pourront se déclencher de nouveau. */
    public static function resetThresholds(Allowance $allowance, string $periodKey): void
    {
        $allowance->alerts()->where('period_key', $periodKey)->delete();
    }

    /** Les forfaits qui comptent une saisie de temps (selon son objet et son jour). */
    public static function allowancesFor(TimeEntry $entry): Collection
    {
        $subject = $entry->subject;
        $consume = (array) config('filament-tma.allowances.consume', ['tickets']);

        $counts = ($subject instanceof Ticket && in_array('tickets', $consume, true))
            || ($subject instanceof ProjectTask && in_array('tasks', $consume, true));

        if (! $counts || ! $entry->billable) {
            return collect();
        }

        return Allowance::query()->get()
            ->filter(fn (Allowance $allowance): bool => $allowance->isActiveOn($entry->worked_on) && $allowance->covers($subject->clientInstanceUid()));
    }

    /** Les saisies qui consomment ce forfait (toutes périodes). */
    private static function entries(Allowance $allowance): Builder
    {
        $instances = $allowance->instanceUids();
        $consume = (array) config('filament-tma.allowances.consume', ['tickets']);

        return TimeEntry::query()
            ->where('billable', true)
            ->where(function (Builder $query) use ($instances, $consume): void {
                if (in_array('tickets', $consume, true)) {
                    $query->orWhereHasMorph('subject', [Ticket::class], fn (Builder $q) => $q->whereIn('source_instance_uid', $instances));
                }

                if (in_array('tasks', $consume, true)) {
                    $query->orWhereHasMorph('subject', [ProjectTask::class], fn (Builder $q) => $q->whereIn('target_instance_uid', $instances));
                }
            });
    }
}
