@php
    use CharlesStOlive\FilamentTma\Models\ProjectTask;

    /**
     * La demande d'un ticket (ou la description d'une tâche), en tête de l'historique : qui l'a écrite, pour qui (au nom
     * de qui, ou à qui quand le Support écrit au client), quand, son texte et ses fichiers. Une tâche s'y lit comme le premier message : son auteur, l'heure de sa
     * publication (prévue, tant qu'elle est en brouillon), sa description.
     *
     * Le premier passage hors du brouillon (`$opening` : transmettre, publier) s'affiche avec elle.
     *
     * @var \CharlesStOlive\FilamentTma\Contracts\Trackable $subject
     * @var \CharlesStOlive\FilamentTma\Models\StateTransition|null $opening
     * @var bool|null $large  des vignettes plus grandes (la grande fenêtre des notes)
     */
    $isTask = $subject instanceof ProjectTask;
    $author = $subject->createdBy ?? ($isTask ? null : $subject->requester);
    $for = ! $isTask && $subject->requester !== null && $author !== null && ! $subject->requester->is($author) ? $subject->requester : null;
    $at = $isTask ? $subject->published_at : ($subject->submitted_at ?? $subject->created_at);
    $scheduled = $isTask && $at === null ? $subject->publish_at : null;
    $timezone = \CharlesStOlive\FilamentTma\Support\Dates::timezone();
@endphp
<header class="fi-tma-entry-header">
    @unless ($isTask)
        <x-filament::badge color="gray" size="sm" :icon="$subject->opening->fromSupport() ? $subject->opening->getIcon() : 'heroicon-m-lifebuoy'">{{ $subject->opening->badge() }}</x-filament::badge>
    @endunless
    @if ($author)
        <strong>{{ $author->name }}</strong>
        @if ($team = $author->team())
            <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
        @endif
    @endif
    @if ($for)
        {{-- Au nom d'un client (un courriel recopié par le Support), adressé à quelqu'un du client, ou pour lui. --}}
        <span class="fi-tma-entry-instance">{{ match (true) {
            $subject->opening === \CharlesStOlive\FilamentTma\Enums\TicketOpening::OnBehalf => __('filament-tma::timeline.request.on_behalf_of', ['name' => $for->name]),
            $subject->opening->fromSupport() => __('filament-tma::timeline.request.to', ['name' => $for->name]),
            default => __('filament-tma::timeline.request.for', ['name' => $for->name]),
        } }}</span>
    @endif
    @if ($opening ?? null)
        @include('filament-tma::timeline.move', ['transition' => $opening, 'author' => $author, 'moveLabels' => $moveLabels, 'states' => $states])
    @endif
    @if ($at)
        <time datetime="{{ $at->toIso8601String() }}">{{ $at->timezone($timezone)->format('d/m/Y H:i') }}</time>
    @elseif ($scheduled)
        <time datetime="{{ $scheduled->toIso8601String() }}">{{ __('filament-tma::timeline.request.publish_at', ['date' => $scheduled->timezone($timezone)->format('d/m/Y H:i')]) }}</time>
    @elseif ($isTask)
        <span class="fi-tma-entry-instance">{{ __('filament-tma::timeline.request.draft') }}</span>
    @endif
</header>
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    @if (filled($subject->description))
        {!! $subject->renderRichContent('description') !!}
    @else
        <p class="fi-tma-timeline-empty">{{ __('filament-tma::timeline.request.no_description') }}</p>
    @endif
</div>
@include('filament-tma::attachments', ['model' => $subject, 'collection' => $subject::ATTACHMENTS_COLLECTION, 'large' => $large ?? false])
