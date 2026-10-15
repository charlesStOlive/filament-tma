@php
    /**
     * Les notes d'un message (ou du ticket entier, `entryId` vide) en vignettes : « n° 3 · Ada Lovelace ». Un clic
     * ouvre la grande fenêtre, la note en avant ; les archivées tiennent en une seule vignette.
     *
     * @var \Illuminate\Support\Collection<int, \CharlesStOlive\FilamentTma\Models\Entry> $notes
     * @var int|null $entryId
     */
    $active = $notes->reject(fn ($note) => $note->isArchived());
    $archived = $notes->count() - $active->count();
@endphp

@if ($notes->isNotEmpty())
    <div class="fi-tma-note-chips">
        @foreach ($active as $note)
            <button
                type="button"
                class="fi-tma-note-chip"
                title="{{ \Illuminate\Support\Str::limit(\CharlesStOlive\FilamentTma\Support\Html::toText($note->body), 160) }}"
                wire:click="mountAction('notes', @js(['entry' => $entryId, 'note' => $note->getKey()]))"
            >
                <x-filament::icon icon="heroicon-m-pencil-square" class="fi-tma-note-chip-icon" />
                <span class="fi-tma-note-chip-number">{{ __('n° :number', ['number' => $note->number]) }}</span>
                @if ($note->private)
                    <x-filament::icon icon="heroicon-m-lock-closed" class="fi-tma-note-chip-icon" :title="__('Visible de vous seul')" />
                @endif
                <span class="fi-tma-note-chip-author">{{ $note->author?->name }}</span>
                @if ($note->getMedia(\CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION)->isNotEmpty())
                    <x-filament::icon icon="heroicon-m-photo" class="fi-tma-note-chip-icon" />
                @endif
            </button>
        @endforeach

        @if ($archived > 0)
            <button
                type="button"
                class="fi-tma-note-chip fi-tma-note-chip--archived"
                wire:click="mountAction('notes', @js(['entry' => $entryId]))"
            >
                <x-filament::icon icon="heroicon-m-archive-box" class="fi-tma-note-chip-icon" />
                {{ trans_choice(':count note archivée|:count notes archivées', $archived, ['count' => $archived]) }}
            </button>
        @endif
    </div>
@endif
