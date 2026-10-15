@php
    use CharlesStOlive\FilamentTma\Models\ProjectTask;
    use CharlesStOlive\FilamentTma\Support\TimelineItems;
    use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
    use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
    use CharlesStOlive\FilamentTma\Workflow\Timeline;
    use Filament\Support\Enums\Size;

    /**
     * L'historique d'un ticket ou d'une tâche : la demande, puis les messages et les changements d'état, par date.
     * Sous la demande et sous chaque message, ses notes (des vignettes qui ouvrent la grande fenêtre, `NoteActions`)
     * et ses boutons : ajouter une note, modifier son message.
     *
     * Un changement d'état fait en même temps qu'un message s'affiche dans le bloc du message :
     * - le premier passage hors du brouillon (transmettre, confier, publier), dans le bloc de la demande ;
     * - un passage qui a écrit son message (la question, la réponse, « Résolu » et son texte : `meta.transition`) ;
     * - un passage qui a un motif (annuler, clore, mettre en pause) devient un bloc, le motif pour texte.
     * Un message écrit à part, même juste après, reste un bloc à lui.
     *
     * @var \CharlesStOlive\FilamentTma\Contracts\Trackable $ticket
     */
    $ticket = $getRecord();
    $items = TimelineItems::for($ticket);
    $transitions = $items->where('kind', 'transition')->pluck('item');
    $opening = $transitions->first(fn ($transition) => $transition->from_state === 'draft' && $transition->to_state !== 'draft');
    // Le passage de chaque message qui en a écrit un ; ces passages-là ne s'affichent pas à part.
    $messageMoves = TimelineItems::movesOfMessages($items->where('kind', 'entry')->pluck('item'), $transitions);
    $shown = $messageMoves->map->ulid->push($opening?->ulid)->filter()->values()->all();
    $states = collect($ticket->state::all())->mapWithKeys(fn ($class, $name) => [$name => new $class($ticket)]);
    $moveLabels = collect($ticket instanceof ProjectTask ? app(TaskWorkflow::class)->moves() : app(TicketWorkflow::class)->moves())
        ->map(fn ($move) => $move->labelFor($ticket));
    $notesOn = Timeline::notesEnabled();
    $ticketNotes = $notesOn ? $ticket->entries()->notes()->visibleTo(auth()->user())->whereNull('parent_id')->with(['author', 'media'])->orderBy('number')->get() : collect();
    // Les notes privées des autres ne se montrent pas.
    $visibleNotes = fn ($notes) => $notes->filter(fn ($note) => $note->isVisibleTo(auth()->user()));

    // Les boutons d'une entrée, rendus seulement quand ils servent (une action cachée s'affiche sinon quand même).
    $buttons = function (array $arguments, array $names): array {
        return collect($names)
            ->map(fn (string $name) => ($this->{$name.'Action'})($arguments)->link()->color('gray')->size(Size::Small))
            ->filter(fn ($action) => $action->isVisible())
            ->all();
    };
@endphp

<div class="fi-tma-timeline">
    <article class="fi-tma-entry fi-tma-entry--request">
        @include('filament-tma::timeline.request', ['subject' => $ticket, 'opening' => $opening, 'moveLabels' => $moveLabels, 'states' => $states])

        @if ($notesOn)
            <footer class="fi-tma-entry-footer">
                @include('filament-tma::notes.chips', ['notes' => $ticketNotes, 'entryId' => null])
                <div class="fi-tma-entry-actions">
                    @foreach ($buttons([], ['createNote']) as $button)
                        {{ $button }}
                    @endforeach
                </div>
            </footer>
        @endif
    </article>

    @foreach ($items as $row)
        @if ($row['kind'] === 'transition' && in_array($row['item']->ulid, $shown, true))
            @continue
        @elseif ($row['kind'] === 'transition' && filled($row['item']->reason))
            @php($transition = $row['item'])
            <article class="fi-tma-entry fi-tma-entry--move" wire:key="tma-move-{{ $transition->getKey() }}">
                <header class="fi-tma-entry-header">
                    <strong>{{ $transition->actor?->name ?? __('Système') }}</strong>
                    @if ($team = $transition->actor?->team())
                        <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
                    @endif
                    @include('filament-tma::timeline.move', ['transition' => $transition, 'author' => $transition->actor])
                    <time datetime="{{ $transition->created_at->toIso8601String() }}">{{ \CharlesStOlive\FilamentTma\Support\Dates::format($transition->created_at) }}</time>
                </header>
                <div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
                    <p>{!! nl2br(e($transition->reason)) !!}</p>
                </div>
            </article>
        @elseif ($row['kind'] === 'transition')
            @php($transition = $row['item'])
            <div class="fi-tma-timeline-event">
                <x-filament::icon icon="heroicon-m-arrow-path" class="fi-tma-timeline-event-icon" />
                <span>
                    <strong>{{ $transition->actor?->name ?? __('Système') }}</strong>
                    @if ($team = $transition->actor?->team())
                        <span class="fi-tma-entry-instance">({{ $team }})</span>
                    @endif
                    — {{ $moveLabels[$transition->move] ?? $transition->move }}
                    @if ($transition->from_state)
                        <x-filament::badge :color="$states[$transition->from_state]?->getColor() ?? 'gray'" size="sm">{{ $states[$transition->from_state]?->getLabel() ?? $transition->from_state }}</x-filament::badge>
                        →
                    @endif
                    <x-filament::badge :color="$states[$transition->to_state]?->getColor() ?? 'gray'" size="sm">{{ $states[$transition->to_state]?->getLabel() ?? $transition->to_state }}</x-filament::badge>
                </span>
                <time datetime="{{ $transition->created_at->toIso8601String() }}">{{ \CharlesStOlive\FilamentTma\Support\Dates::format($transition->created_at) }}</time>
            </div>
        @else
            @php($entry = $row['item'])
            <article class="fi-tma-entry" id="tma-entry-{{ $entry->getKey() }}" wire:key="tma-entry-{{ $entry->getKey() }}">
                @include('filament-tma::timeline.entry', ['entry' => $entry, 'transition' => $messageMoves->get($entry->getKey())])

                @foreach ($entry->replies as $reply)
                    <div class="fi-tma-entry-reply">
                        @include('filament-tma::timeline.entry', ['entry' => $reply, 'transition' => $messageMoves->get($reply->getKey())])
                    </div>
                @endforeach

                @php($entryButtons = $buttons(['entry' => $entry->getKey()], $notesOn ? ['editMessage', 'createNote'] : ['editMessage']))
                @php($entryNotes = $notesOn ? $visibleNotes($entry->notes) : collect())
                @if ($entryNotes->isNotEmpty() || $entryButtons !== [])
                    <footer class="fi-tma-entry-footer">
                        @if ($notesOn)
                            @include('filament-tma::notes.chips', ['notes' => $entryNotes, 'entryId' => $entry->getKey()])
                        @endif
                        <div class="fi-tma-entry-actions">
                            @foreach ($entryButtons as $button)
                                {{ $button }}
                            @endforeach
                        </div>
                    </footer>
                @endif
            </article>
        @endif
    @endforeach
</div>
