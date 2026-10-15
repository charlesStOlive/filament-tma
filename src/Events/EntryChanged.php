<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Foundation\Events\Dispatchable;

/** Un message ou une note a été modifié ; une note a été archivée, ressortie ou supprimée. */
class EntryChanged
{
    use Dispatchable;

    public const EDITED = 'edited';

    public const ARCHIVED = 'archived';

    public const RESTORED = 'restored';

    public const DELETED = 'deleted';

    public function __construct(public Entry $entry, public Context $context, public string $change) {}
}
