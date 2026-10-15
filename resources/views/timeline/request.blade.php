@php
    use CharlesStOlive\FilamentTma\Models\ProjectTask;

    /**
     * La demande d'un ticket (ou la description d'une tâche), en tête de l'historique : qui l'a écrite, pour qui,
     * quand, son texte et ses fichiers. Une tâche s'y lit comme le premier message : son auteur, l'heure de sa
     * publication (prévue, tant qu'elle est en brouillon), sa description.
     *
     * Le premier passage hors du brouillon (`$opening` : transmettre, confier, publier) s'affiche avec elle.
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
        <x-filament::badge color="gray" size="sm" icon="heroicon-m-lifebuoy">{{ __('Demande') }}</x-filament::badge>
    @endunless
    @if ($author)
        <strong>{{ $author->name }}</strong>
        @if ($team = $author->team())
            <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
        @endif
    @endif
    @if ($for)
        <span class="fi-tma-entry-instance">{{ __('pour :name', ['name' => $for->name]) }}</span>
    @endif
    @if ($opening ?? null)
        @include('filament-tma::timeline.move', ['transition' => $opening, 'author' => $author, 'moveLabels' => $moveLabels, 'states' => $states])
    @endif
    @if ($at)
        <time datetime="{{ $at->toIso8601String() }}">{{ $at->timezone($timezone)->format('d/m/Y H:i') }}</time>
    @elseif ($scheduled)
        <time datetime="{{ $scheduled->toIso8601String() }}">{{ __('publication prévue le :date', ['date' => $scheduled->timezone($timezone)->format('d/m/Y H:i')]) }}</time>
    @elseif ($isTask)
        <span class="fi-tma-entry-instance">{{ __('brouillon, pas encore publié') }}</span>
    @endif
</header>
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    @if (filled($subject->description))
        {!! $subject->renderRichContent('description') !!}
    @else
        <p class="fi-tma-timeline-empty">{{ __('Pas de description.') }}</p>
    @endif
</div>
@include('filament-tma::attachments', ['model' => $subject, 'collection' => $subject::ATTACHMENTS_COLLECTION, 'large' => $large ?? false])
