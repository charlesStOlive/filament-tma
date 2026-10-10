@php
    use CharlesStOlive\FilamentTma\Support\TimelineItems;
    use CharlesStOlive\FilamentTma\Workflow\Context;
    use CharlesStOlive\FilamentTma\Workflow\Timeline;

    /** @var \CharlesStOlive\FilamentTma\Contracts\Trackable $ticket  un ticket ou une tâche projet */
    $ticket = $getRecord();
    $context = Context::current();
    $timeline = app(Timeline::class);
    $withNotes = $timeline->canReadNotes($ticket, $context);
    $items = TimelineItems::for($ticket, $withNotes);
    $states = collect($ticket->state::all())->mapWithKeys(fn ($class, $name) => [$name => new $class($ticket)]);
    $moveLabels = collect($ticket instanceof \CharlesStOlive\FilamentTma\Models\ProjectTask
        ? app(\CharlesStOlive\FilamentTma\Workflow\TaskWorkflow::class)->moves()
        : app(\CharlesStOlive\FilamentTma\Workflow\TicketWorkflow::class)->moves())->map(fn ($move) => $move->labelFor($ticket));
@endphp

<div class="fi-tma-timeline">
    @forelse ($items as $row)
        @if ($row['kind'] === 'transition')
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
                    @if ($transition->reason)
                        <em class="fi-tma-timeline-reason">« {{ $transition->reason }} »</em>
                    @endif
                </span>
                <time datetime="{{ $transition->created_at->toIso8601String() }}">{{ $transition->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
            </div>
        @else
            @php($entry = $row['item'])
            <article @class(['fi-tma-entry', 'fi-tma-entry--'.$entry->type->value, 'fi-tma-entry--local' => ! $entry->isShared()])>
                @include('filament-tma::timeline.entry', ['entry' => $entry])

                @foreach ($entry->replies as $reply)
                    <div class="fi-tma-entry-reply">
                        @include('filament-tma::timeline.entry', ['entry' => $reply])
                    </div>
                @endforeach

                {{-- On répond dans la zone de réponse, sous la chronologie ; ici, seulement partager une note. Les
                     directives Blade ne s'appliquent pas dans les attributs d'un composant : l'argument est écrit en JS. --}}
                @if ($timeline->canShare($entry, $context))
                    <div class="fi-tma-entry-actions">
                        <x-filament::link tag="button" size="sm" color="warning" icon="heroicon-m-share" wire:click="mountAction('shareNote', { entry: {{ (int) $entry->getKey() }} })">
                            {{ __('Partager avec l’autre équipe') }}
                        </x-filament::link>
                    </div>
                @endif
            </article>
        @endif
    @empty
        <p class="fi-tma-timeline-empty">{{ __('Rien pour l’instant.') }}</p>
    @endforelse
</div>
