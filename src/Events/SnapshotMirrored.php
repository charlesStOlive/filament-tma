<?php

namespace CharlesStOlive\FilamentTma\Events;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * L'état d'un ticket ou d'une tâche, reçu de l'autre application, a apporté des passages ou des entrées qui n'étaient
 * pas encore ici (`Sync\Mirror::snapshot()`).
 */
class SnapshotMirrored
{
    use Dispatchable;

    /**
     * @param  array<int, StateTransition>  $transitions
     * @param  array<int, Entry>  $entries
     */
    public function __construct(public Trackable $subject, public array $transitions, public array $entries) {}
}
