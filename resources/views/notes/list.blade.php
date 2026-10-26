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
        ->label($subject instanceof ProjectTask ? __('filament-tma::notes.list.on_task') : __('filament-tma::notes.list.on_ticket'))
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
                {{ $showArchived ? __('filament-tma::notes.list.hide_archived') : trans_choice('filament-tma::notes.list.show_archived', $archivedCount, ['count' => $archivedCount]) }}
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
                <span class="fi-tma-note-number">{{ __('filament-tma::notes.card.number', ['number' => $note->number]) }}</span>
                @if ($note->private)
                    <x-filament::icon icon="heroicon-m-lock-closed" class="fi-tma-note-chip-icon" :title="__('filament-tma::notes.card.private')" />
                @endif
                <strong class="fi-tma-notes-list-author">{{ $note->author?->name }}</strong>
                <time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->timezone($timezone)->format('d/m H:i') }}</time>
            </span>
            <span class="fi-tma-notes-list-target">
                @if ($note->parent)
                    {{ __('filament-tma::notes.list.on_message', ['author' => $note->parent->author?->name ?? '?', 'date' => $note->parent->created_at->timezone($timezone)->format('d/m')]) }}
                @else
                    {{ $subject instanceof ProjectTask ? __('filament-tma::notes.list.task') : __('filament-tma::notes.list.ticket') }}
                @endif
                @if ($note->isArchived())
                    · {{ __('filament-tma::notes.list.archived') }}
                @endif
                @if ($note->private)
                    · {{ __('filament-tma::notes.list.private') }}
                @endif
            </span>
            @if (filled($text = Html::toText($note->body)))
                <span class="fi-tma-notes-list-excerpt">{{ Str::limit($text, 140) }}</span>
            @endif
            @if ($images > 0)
                <span class="fi-tma-notes-list-images">
                    <x-filament::icon icon="heroicon-m-photo" class="fi-tma-note-chip-icon" />
                    {{ trans_choice('filament-tma::notes.list.images', $images, ['count' => $images]) }}
                </span>
            @endif
        </button>
    @empty
        <p class="fi-tma-timeline-empty">{{ $showArchived ? __('filament-tma::notes.list.empty') : __('filament-tma::notes.list.empty_yet') }}</p>
    @endforelse
</div>
