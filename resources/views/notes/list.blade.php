@php
    use CharlesStOlive\FilamentTma\Models\Entry;
    use CharlesStOlive\FilamentTma\Models\ProjectTask;
    use CharlesStOlive\FilamentTma\Support\Html;
    use Filament\Support\Enums\Size;
    use Illuminate\Support\Str;

    /**
     * L'onglet « Notes » du volet : toutes les notes de la fiche en aperçu, par numéro. Un clic ouvre la note dans la
     * grande fenêtre, à côté du message qu'elle commente.
     *
     * @var \CharlesStOlive\FilamentTma\Contracts\Trackable $subject
     */
    $subject = $getRecord();
    $showArchived = (bool) ($this->showArchivedNotes ?? false);
    $notes = $subject->noteEntries(auth()->user(), withArchived: $showArchived);
    $archivedCount = $subject->entries()->notes()->visibleTo(auth()->user())->whereNotNull('archived_at')->count();
    $create = ($this->createNoteAction)([])
        ->label($subject instanceof ProjectTask ? __('Note sur la tâche') : __('Note sur le ticket'))
        ->size(Size::Small);
    $timezone = \CharlesStOlive\FilamentTma\Support\Dates::timezone();
@endphp

<div class="fi-tma-notes-list">
    <div class="fi-tma-notes-list-toolbar">
        @if ($create->isVisible())
            {{ $create }}
        @endif
        @if ($archivedCount > 0)
            <x-filament::link tag="button" size="sm" color="gray" icon="heroicon-m-archive-box" wire:click="$toggle('showArchivedNotes')">
                {{ $showArchived ? __('Masquer les archivées') : trans_choice('Voir l’archivée|Voir les :count archivées', $archivedCount, ['count' => $archivedCount]) }}
            </x-filament::link>
        @endif
    </div>

    @forelse ($notes as $note)
        @php($images = $note->getMedia(Entry::ATTACHMENTS_COLLECTION)->count())
        <button
            type="button"
            @class(['fi-tma-notes-list-item', 'fi-tma-notes-list-item--archived' => $note->isArchived()])
            wire:key="tma-notes-list-{{ $note->getKey() }}"
            wire:click="mountAction('notes', @js(['entry' => $note->parent_id, 'note' => $note->getKey()]))"
        >
            <span class="fi-tma-notes-list-head">
                <span class="fi-tma-note-number">{{ __('n° :number', ['number' => $note->number]) }}</span>
                @if ($note->private)
                    <x-filament::icon icon="heroicon-m-lock-closed" class="fi-tma-note-chip-icon" :title="__('Visible de vous seul')" />
                @endif
                <strong class="fi-tma-notes-list-author">{{ $note->author?->name }}</strong>
                <time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->timezone($timezone)->format('d/m H:i') }}</time>
            </span>
            <span class="fi-tma-notes-list-target">
                @if ($note->parent)
                    {{ __('Sur le message de :author du :date', ['author' => $note->parent->author?->name ?? '?', 'date' => $note->parent->created_at->timezone($timezone)->format('d/m')]) }}
                @else
                    {{ $subject instanceof ProjectTask ? __('Sur la tâche') : __('Sur le ticket') }}
                @endif
                @if ($note->isArchived())
                    · {{ __('archivée') }}
                @endif
                @if ($note->private)
                    · {{ __('visible de vous seul') }}
                @endif
            </span>
            @if (filled($text = Html::toText($note->body)))
                <span class="fi-tma-notes-list-excerpt">{{ Str::limit($text, 140) }}</span>
            @endif
            @if ($images > 0)
                <span class="fi-tma-notes-list-images">
                    <x-filament::icon icon="heroicon-m-photo" class="fi-tma-note-chip-icon" />
                    {{ trans_choice(':count image|:count images', $images, ['count' => $images]) }}
                </span>
            @endif
        </button>
    @empty
        <p class="fi-tma-timeline-empty">{{ $showArchived ? __('Aucune note.') : __('Aucune note pour l’instant.') }}</p>
    @endforelse
</div>
