<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Une note a été partagée avec l'autre partie. */
class NoteShared
{
    use Dispatchable;

    public function __construct(public Entry $note, public Context $context) {}
}
