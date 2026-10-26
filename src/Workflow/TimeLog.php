<?php

namespace CharlesStOlive\FilamentTma\Workflow;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisir du temps sur un ticket (maître seulement, addendum §5), avec le droit `tma.ticket.time`. Le temps se
 * saisit aussi sur un ticket terminé : on y reporte souvent une intervention après coup.
 *
 * Une saisie ne dit pas elle-même ce qui a été fait : elle porte sur un message ou une note de la fiche, ou sur la
 * fiche entière ; sur une tâche projet, aussi sur un message écrit avec elle (`addWithMessage()`).
 */
final class TimeLog
{
    public function canLog(Trackable $ticket, Context $context): bool
    {
        return Tma::isMaster()
            && $ticket->isHandledHere()
            && ($context->isSystem() || TmaGate::allows($context->user, TmaGate::TIME, $ticket));
    }

    /** @param  Entry|null  $entry  le message ou la note sur lequel porte ce temps (de la même fiche) */
    public function add(Trackable $ticket, int $minutes, \DateTimeInterface|string|null $workedOn, Context $context, bool $billable = true, ?Entry $entry = null): TimeEntry
    {
        $this->assertCanAdd($ticket, $minutes, $workedOn, $context, $billable);
        self::assertEntryOf($ticket, $entry);

        $time = $ticket->timeEntries()->create([
            'actor_id' => $context->actor?->getKey() ?? throw new \LogicException('Une saisie de temps a toujours un intervenant.'),
            'worked_on' => $workedOn !== null ? Carbon::parse($workedOn)->toDateString() : now()->toDateString(),
            'minutes' => $minutes,
            'billable' => $billable,
            'origin' => $context->origin,
            'entry_id' => $entry?->getKey(),
        ]);

        $ticket->touchActivity();

        return $time;
    }

    /**
     * Le temps et le message qui dit ce qui a été fait (ou une note, avec `$type`), écrit dans l'historique de la
     * fiche : le temps porte dessus. Ensemble, ou pas du tout ; tout est vérifié avant d'écrire.
     */
    public function addWithMessage(Trackable $ticket, int $minutes, \DateTimeInterface|string|null $workedOn, string $body, Context $context, bool $billable = true, EntryType $type = EntryType::Comment): TimeEntry
    {
        $this->assertCanAdd($ticket, $minutes, $workedOn, $context, $billable);

        // Sur un ticket, un message va avec un passage : le temps n'y a pas de texte (addendum §3.2).
        if ($ticket instanceof Ticket) {
            throw ValidationException::withMessages(['message' => __('filament-tma::time.errors.ticket_time_needs_target')]);
        }

        if (! app(Timeline::class)->canAdd($ticket, $type, $context)) {
            throw new AuthorizationException($type === EntryType::Note
                ? __('filament-tma::time.errors.cannot_write_note')
                : __('filament-tma::time.errors.ticket_closed_for_messages'));
        }

        return DB::transaction(fn (): TimeEntry => $this->add(
            $ticket, $minutes, $workedOn, $context, $billable,
            app(Timeline::class)->add($ticket, $type, $body, $context),
        ));
    }

    private function assertCanAdd(Trackable $ticket, int $minutes, \DateTimeInterface|string|null $workedOn, Context $context, bool $billable): void
    {
        if (! $this->canLog($ticket, $context)) {
            throw new AuthorizationException(__('filament-tma::common.cannot_log_time'));
        }

        if ($minutes <= 0 || $minutes > 24 * 60) {
            throw ValidationException::withMessages(['minutes' => __('filament-tma::time.errors.range')]);
        }

        $this->assertWithinAllowance($ticket, $minutes, $workedOn, $billable);
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
                throw ValidationException::withMessages(['minutes' => __('filament-tma::time.errors.allowance_exceeded', ['label' => $allowance->label()])]);
            }
        }
    }

    /** Un temps porte sur un message ou une note de la même fiche. */
    public static function assertEntryOf(Trackable $subject, ?Entry $entry): void
    {
        if ($entry === null || ($entry->subject_type === $subject->getMorphClass() && (int) $entry->subject_id === (int) $subject->getKey())) {
            return;
        }

        throw ValidationException::withMessages(['entry_id' => __('filament-tma::time.errors.message_not_on_record')]);
    }
}
