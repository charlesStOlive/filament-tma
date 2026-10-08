<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\TimeEntry;
use Illuminate\Foundation\Events\Dispatchable;

/** Une saisie de temps a été ajoutée, modifiée ou supprimée (`action` : created, updated, deleted). */
class TimeEntryChanged
{
    use Dispatchable;

    public function __construct(public TimeEntry $entry, public string $action) {}
}
