<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Support\Tma;

/** Après chaque saisie de temps (maître) : les seuils des forfaits concernés, pour la période de la saisie. */
class CheckAllowances
{
    public function handle(TimeEntryChanged $event): void
    {
        if (! Tma::isMaster() || $event->action === 'deleted') {
            return;
        }

        AllowanceMeter::allowancesFor($event->entry)->each(
            fn (Allowance $allowance) => AllowanceMeter::checkThresholds($allowance, AllowanceMeter::periodKey($allowance, $event->entry->worked_on)),
        );
    }
}
