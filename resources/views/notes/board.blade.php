@php
    use CharlesStOlive\FilamentTma\Models\ProjectTask;
    use Filament\Support\Enums\Size;

    /**
     * La grande fenêtre des notes (`NoteActions::board()`) : à gauche, le message (ou la demande, pour les notes sur
     * le ticket entier) avec ses fichiers en vignettes ; à droite, ses notes. Les boutons ouvrent leur fenêtre
     * par-dessus celle-ci, qui se met à jour en revenant.
     *
     * @var \CharlesStOlive\FilamentTma\Contracts\Trackable $subject
     * @var \CharlesStOlive\FilamentTma\Models\Entry|null $message
     * @var int $focus  la note à montrer
     * @var \Filament\Actions\Action $action  la grande fenêtre, qui déclare les boutons des notes
     */
    $notes = ($message !== null ? $message->notes() : $subject->entries()->notes()->whereNull('parent_id')->orderBy('number'))
        ->visibleTo(auth()->user())
        ->with(['author', 'media'])
        ->get();
    $active = $notes->reject(fn ($note) => $note->isArchived());
    $archived = $notes->filter(fn ($note) => $note->isArchived());
    $create = ($action->getModalAction('createNote'))($message !== null ? ['entry' => $message->getKey()] : [])->size(Size::Small);
@endphp

<div class="fi-tma-board">
    <section class="fi-tma-board-subject">
        <article class="fi-tma-entry">
            @if ($message !== null)
                @include('filament-tma::timeline.entry', ['entry' => $message, 'large' => true])
            @else
                @include('filament-tma::timeline.request', ['subject' => $subject, 'large' => true])
            @endif
        </article>
    </section>

    <aside class="fi-tma-board-notes">
        <header class="fi-tma-board-notes-header">
            <h3>
                @if ($message !== null)
                    {{ __('Notes sur ce message') }}
                @else
                    {{ $subject instanceof ProjectTask ? __('Notes sur la tâche') : __('Notes sur le ticket') }}
                @endif
                @if ($active->isNotEmpty())
                    <x-filament::badge color="warning" size="sm">{{ $active->count() }}</x-filament::badge>
                @endif
            </h3>
            @if ($create->isVisible())
                {{ $create }}
            @endif
        </header>

        @forelse ($active as $note)
            @include('filament-tma::notes.card', ['note' => $note, 'focus' => $focus, 'action' => $action])
        @empty
            <p class="fi-tma-timeline-empty">{{ __('Pas encore de note.') }}</p>
        @endforelse

        @if ($archived->isNotEmpty())
            <details class="fi-tma-board-archived" @if ($archived->contains(fn ($note) => $note->getKey() === $focus)) open @endif>
                <summary>{{ trans_choice(':count note archivée|:count notes archivées', $archived->count(), ['count' => $archived->count()]) }}</summary>
                @foreach ($archived as $note)
                    @include('filament-tma::notes.card', ['note' => $note, 'focus' => $focus, 'action' => $action])
                @endforeach
            </details>
        @endif
    </aside>
</div>
