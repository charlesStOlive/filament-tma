<?php

namespace CharlesStOlive\FilamentTma\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** `tma:cleanup` a fini (CDC §22) ; `report` donne les nombres par catégorie, `dryRun` si rien n'a été supprimé. */
class CleanupFinished
{
    use Dispatchable;

    /** @param  array<string, int>  $report */
    public function __construct(public array $report, public bool $dryRun) {}
}
