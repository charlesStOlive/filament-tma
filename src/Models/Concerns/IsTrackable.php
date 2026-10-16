<?php

namespace CharlesStOlive\FilamentTma\Models\Concerns;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Contracts\Auth\Authenticatable;
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

    /** Les destinataires : principaux et secondaires (`Workflow\Recipients`). */
    public function recipients(): MorphMany
    {
        return $this->morphMany(Recipient::class, 'subject');
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

    /** Les messages de premier niveau, avec leurs réponses et leurs notes. */
    public function timelineEntries(): Collection
    {
        return $this->entries()
            ->messages()
            ->whereNull('parent_id')
            ->with(['author', 'media', 'replies' => fn ($q) => $q->with(['author', 'media']), 'notes' => fn ($q) => $q->with(['author', 'media'])])
            ->oldest()
            ->get();
    }

    /** Les notes qu'une personne peut lire, par numéro : sur le ticket entier ou sur un message ; les archivées sur demande. */
    public function noteEntries(?Authenticatable $user, bool $withArchived = false): Collection
    {
        return $this->entries()
            ->notes()
            ->visibleTo($user)
            ->when(! $withArchived, fn ($q) => $q->whereNull('archived_at'))
            ->with(['author', 'media', 'parent.author'])
            ->orderBy('number')
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
