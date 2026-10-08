<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Un commentaire, une note, une réponse ou un résumé a été ajouté à une chronologie. */
class EntryAdded
{
    use Dispatchable;

    public function __construct(public Entry $entry, public Context $context) {}
}
