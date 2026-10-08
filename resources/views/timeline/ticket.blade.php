@php
    use CharlesStOlive\FilamentTma\Enums\EntryType;
    use CharlesStOlive\FilamentTma\Support\TimelineItems;
    use CharlesStOlive\FilamentTma\Workflow\Context;
    use CharlesStOlive\FilamentTma\Workflow\Timeline;

    /** @var \CharlesStOlive\FilamentTma\Models\Ticket $ticket */
    $ticket = $getRecord();
    $context = Context::current();
    $timeline = app(Timeline::class);
    $withNotes = $timeline->canReadNotes($ticket, $context);
    $canComment = $timeline->canAdd($ticket, EntryType::Comment, $context);
    $canNote = $timeline->canAdd($ticket, EntryType::Note, $context);
    $items = TimelineItems::for($ticket, $withNotes);
    $states = collect($ticket->state::all())->mapWithKeys(fn ($class, $name) => [$name => new $class($ticket)]);
@endphp

<div class="fi-tma-timeline">
    @forelse ($items as $row)
        @if ($row['kind'] === 'transition')
            @php($transition = $row['item'])
            <div class="fi-tma-timeline-event">
                <x-filament::icon icon="heroicon-m-arrow-path" class="fi-tma-timeline-event-icon" />
                <span>
                    <strong>{{ $transition->actor?->name ?? __('Système') }}</strong>
                    — {{ app(\CharlesStOlive\FilamentTma\Workflow\TicketWorkflow::class)->moves()[$transition->move]?->label() ?? $transition->move }}
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

                <div class="fi-tma-entry-actions">
                    @if (($entry->type === EntryType::Note && $canNote) || ($entry->type !== EntryType::Note && $canComment))
                        <x-filament::link tag="button" size="sm" icon="heroicon-m-arrow-uturn-left" wire:click="mountAction('reply', @js(['entry' => $entry->getKey()]))">
                            {{ __('Répondre') }}
                        </x-filament::link>
                    @endif
                    @if ($timeline->canShare($entry, $context))
                        <x-filament::link tag="button" size="sm" color="warning" icon="heroicon-m-share" wire:click="mountAction('shareNote', @js(['entry' => $entry->getKey()]))">
                            {{ __('Partager') }}
                        </x-filament::link>
                    @endif
                </div>
            </article>
        @endif
    @empty
        <p class="fi-tma-timeline-empty">{{ __('Rien pour l’instant.') }}</p>
    @endforelse
</div>
