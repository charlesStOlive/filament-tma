<?php

namespace CharlesStOlive\FilamentTma\Models\Concerns;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/** Les relations et aides communes à un objet suivi (`Contracts\Trackable`). */
trait IsTrackable
{
    public function entries(): MorphMany
    {
        return $this->morphMany(Entry::class, 'subject');
    }

    public function transitions(): MorphMany
    {
        return $this->morphMany(StateTransition::class, 'subject');
    }

    /** Les saisies de temps (dans le maître ; un client n'a que les totaux, `time_total_minutes`). */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'subject');
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now(), ...($this->hasAutoCloseWarning() ? ['auto_close_warned_at' => null] : [])])->saveQuietly();
    }

    protected function hasAutoCloseWarning(): bool
    {
        return false;
    }

    /** Les entrées de premier niveau visibles ici, avec leurs réponses. */
    public function timelineEntries(bool $withNotes): Collection
    {
        return $this->entries()
            ->whereNull('parent_id')
            ->visibleHere($withNotes)
            ->with(['author', 'media', 'replies' => fn ($q) => $q->visibleHere($withNotes)->with(['author', 'media'])])
            ->oldest()
            ->get();
    }

    public function latestEntryOfType(EntryType $type): ?Entry
    {
        return $this->entries()->where('type', $type->value)->latest('id')->first();
    }

    public function sidesHere(): array
    {
        return $this->sidesOf(Tma::instanceUid(), Tma::isMaster());
    }

    public function isHandledHere(): bool
    {
        return in_array(Side::Handler, $this->sidesHere(), true);
    }
}
