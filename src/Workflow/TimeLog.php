<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Saisir du temps sur un ticket (maître seulement, addendum §5), avec le droit `tma.ticket.time`. Le temps se
 * saisit aussi sur un ticket terminé : on y reporte souvent une intervention après coup.
 */
final class TimeLog
{
    public function canLog(Trackable $ticket, Context $context): bool
    {
        return Tma::isMaster()
            && $ticket->isHandledHere()
            && ($context->isSystem() || TmaGate::allows($context->user, TmaGate::TIME, $ticket));
    }

    public function add(Trackable $ticket, int $minutes, \DateTimeInterface|string|null $workedOn, ?string $description, Context $context, bool $billable = true): TimeEntry
    {
        if (! $this->canLog($ticket, $context)) {
            throw new AuthorizationException(__('Vous ne pouvez pas saisir de temps sur ce ticket.'));
        }

        if ($minutes <= 0 || $minutes > 24 * 60) {
            throw ValidationException::withMessages(['minutes' => __('Une saisie va d’une minute à 24 heures.')]);
        }

        $this->assertWithinAllowance($ticket, $minutes, $workedOn, $billable);

        $entry = $ticket->timeEntries()->create([
            'actor_id' => $context->actor?->getKey() ?? throw new \LogicException('Une saisie de temps a toujours un intervenant.'),
            'worked_on' => $workedOn !== null ? Carbon::parse($workedOn)->toDateString() : now()->toDateString(),
            'minutes' => $minutes,
            'description' => $description,
            'billable' => $billable,
            'origin' => $context->origin,
        ]);

        $ticket->touchActivity();

        return $entry;
    }

    /** Avec `allowances.block_overage`, une saisie facturable qui ferait dépasser 100 % d'un forfait est refusée. */
    private function assertWithinAllowance(Trackable $subject, int $minutes, \DateTimeInterface|string|null $workedOn, bool $billable): void
    {
        if (! $billable || ! config('filament-tma.allowances.block_overage', false)) {
            return;
        }

        $probe = new TimeEntry(['worked_on' => $workedOn ?? now(), 'billable' => true]);
        $probe->setRelation('subject', $subject);

        foreach (AllowanceMeter::allowancesFor($probe) as $allowance) {
            $key = AllowanceMeter::periodKey($allowance, $probe->worked_on);

            if (AllowanceMeter::consumed($allowance, $key) + $minutes > $allowance->minutes) {
                throw ValidationException::withMessages(['minutes' => __('Le forfait « :label » serait dépassé.', ['label' => $allowance->label()])]);
            }
        }
    }
}
