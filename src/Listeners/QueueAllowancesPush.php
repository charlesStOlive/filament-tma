<?php

namespace CharlesStOlive\FilamentTma\Listeners;

use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Jobs\PushAllowancesToInstance;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Support\Tma;

/** Une saisie de temps change la consommation : l'état des forfaits concernés part vers leurs applications. */
class QueueAllowancesPush
{
    public function handle(TimeEntryChanged $event): void
    {
        if (! Tma::isMaster()) {
            return;
        }

        // Une saisie passée en non facturable a pu quitter le forfait : on renvoie quand même (sur une copie).
        $probe = $event->entry->replicate()->forceFill(['billable' => true]);
        $probe->setRelation('subject', $event->entry->subject);

        AllowanceMeter::allowancesFor($probe)->each(fn (Allowance $allowance) => PushAllowancesToInstance::dispatchFor($allowance));
    }
}
