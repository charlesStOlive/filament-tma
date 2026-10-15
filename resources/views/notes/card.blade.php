@php
    use Filament\Support\Enums\Size;

    /**
     * Une note, en lecture, dans la grande fenêtre : son numéro, son auteur, son texte, ses images ; et ses boutons
     * (modifier et supprimer pour son auteur, archiver pour tous ; tout pour son auteur seul si elle est privée), qui
     * ouvrent leur fenêtre par-dessus.
     *
     * @var \CharlesStOlive\FilamentTma\Models\Entry $note
     * @var int $focus
     * @var \Filament\Actions\Action $action  la grande fenêtre
     */
    $timezone = \CharlesStOlive\FilamentTma\Support\Dates::timezone();
    $buttons = collect(['editNote', 'archiveNote', 'deleteNote'])
        ->map(fn (string $name) => ($action->getModalAction($name))(['note' => $note->getKey()])->link()->size(Size::Small))
        ->filter(fn ($button) => $button->isVisible());
@endphp

<article
    @class(['fi-tma-note', 'fi-tma-note--focused' => $note->getKey() === $focus, 'fi-tma-note--archived' => $note->isArchived()])
    wire:key="tma-note-{{ $note->getKey() }}"
    @if ($note->getKey() === $focus) x-init="$nextTick(() => $el.scrollIntoView({ block: 'nearest' }))" @endif
>
    <header class="fi-tma-entry-header">
        <x-filament::badge color="warning" size="sm" icon="heroicon-m-pencil-square">{{ __('n° :number', ['number' => $note->number]) }}</x-filament::badge>
        <strong>{{ $note->author?->name }}</strong>
        @if ($team = $note->author?->team())
            <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
        @endif
        @if ($note->private)
            <x-filament::badge color="gray" size="sm" icon="heroicon-m-lock-closed">{{ __('Visible de vous seul') }}</x-filament::badge>
        @endif
        @if ($note->isArchived())
            <x-filament::badge color="gray" size="sm" icon="heroicon-m-archive-box">{{ __('Archivée') }}</x-filament::badge>
        @endif
        @include('filament-tma::timeline.time', ['entry' => $note])
        @if ($note->edited_at)
            <span class="fi-tma-entry-edited" title="{{ $note->edited_at->timezone($timezone)->format('d/m/Y H:i') }}">{{ __('modifiée') }}</span>
        @endif
        <time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->timezone($timezone)->format('d/m/Y H:i') }}</time>
    </header>

    @if (! \CharlesStOlive\FilamentTma\Support\Html::isBlank($note->body))
        <div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
            {!! $note->renderRichContent('body') !!}
        </div>
    @endif
    @include('filament-tma::attachments', ['model' => $note, 'collection' => \CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION, 'large' => true])

    @if ($buttons->isNotEmpty())
        <footer class="fi-tma-entry-actions">
            @foreach ($buttons as $button)
                {{ $button }}
            @endforeach
        </footer>
    @endif
</article>
