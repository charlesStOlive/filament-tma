@php
    /**
     * Un message : son auteur, le changement d'état fait avec lui s'il y en a un, sa date (et quand il a été modifié), le
     * temps saisi dessus (maître), son texte, ses fichiers.
     *
     * @var \CharlesStOlive\FilamentTma\Models\Entry $entry
     * @var \CharlesStOlive\FilamentTma\Models\StateTransition|null $transition  le passage fait avec ce message
     * @var bool|null $large  des vignettes plus grandes (la grande fenêtre des notes)
     */
    $timezone = \CharlesStOlive\FilamentTma\Support\Dates::timezone();
@endphp
<header class="fi-tma-entry-header">
    <strong>{{ $entry->author?->name }}</strong>
    @if ($team = $entry->author?->team())
        <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
    @endif
    @if ($transition ?? null)
        @include('filament-tma::timeline.move', ['transition' => $transition, 'author' => $entry->author])
    @endif
    @if ($entry->edited_at)
        <span class="fi-tma-entry-edited" title="{{ $entry->edited_at->timezone($timezone)->format('d/m/Y H:i') }}">{{ __('modifié') }}</span>
    @endif
    @include('filament-tma::timeline.time', ['entry' => $entry])
    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone($timezone)->format('d/m/Y H:i') }}</time>
</header>
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    {!! $entry->renderRichContent('body') !!}
</div>
@include('filament-tma::attachments', ['model' => $entry, 'collection' => \CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION, 'large' => $large ?? false])
