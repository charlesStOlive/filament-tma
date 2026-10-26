@php
    /**
     * Un message : son auteur, le changement d'état fait avec lui s'il y en a un, sa date (et quand il a été modifié), le
     * temps saisi dessus (maître), son texte, ses fichiers. Une question du Support dit où elle en est, et à qui elle est
     * posée.
     *
     * @var \CharlesStOlive\FilamentTma\Models\Entry $entry
     * @var \CharlesStOlive\FilamentTma\Models\StateTransition|null $transition  le passage fait avec ce message
     * @var bool|null $large  des vignettes plus grandes (la grande fenêtre des notes)
     */
    $timezone = \CharlesStOlive\FilamentTma\Support\Dates::timezone();
    $questionStatus = \CharlesStOlive\FilamentTma\Workflow\Questions::status($entry);
    [$questionColor, $questionLabel] = match ($questionStatus) {
        'open' => ['warning', __('filament-tma::timeline.question.pending')],
        'answered' => ['success', __('filament-tma::timeline.question.answered')],
        default => ['gray', __('filament-tma::common.question_withdrawn')],
    };
@endphp
<header class="fi-tma-entry-header">
    <strong>{{ $entry->author?->name }}</strong>
    @if ($team = $entry->author?->team())
        <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
    @endif
    @if ($transition ?? null)
        @include('filament-tma::timeline.move', ['transition' => $transition, 'author' => $entry->author])
    @endif
    @if (! empty($entry->meta['reminder']))
        <x-filament::badge color="warning" size="sm" icon="heroicon-m-bell-alert">{{ __('filament-tma::common.reminder') }}</x-filament::badge>
    @endif
    @if (! empty($entry->meta['notify']))
        <x-filament::badge color="info" size="sm" icon="heroicon-m-envelope">{{ __('filament-tma::timeline.sent_by_email') }}</x-filament::badge>
    @endif
    @if ($questionStatus !== null)
        <x-filament::badge :color="$questionColor" size="sm" icon="heroicon-m-question-mark-circle">{{ $questionLabel }}</x-filament::badge>
    @endif
    @if ($entry->edited_at)
        <span class="fi-tma-entry-edited" title="{{ $entry->edited_at->timezone($timezone)->format('d/m/Y H:i') }}">{{ __('filament-tma::timeline.edited') }}</span>
    @endif
    @include('filament-tma::timeline.time', ['entry' => $entry])
    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone($timezone)->format('d/m/Y H:i') }}</time>
</header>
@if ($questionStatus !== null)
    <p class="fi-tma-entry-question-to">{{ __('filament-tma::timeline.question.to', ['names' => implode(', ', \CharlesStOlive\FilamentTma\Workflow\Questions::names($entry)) ?: '—']) }}</p>
@endif
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    {!! $entry->renderRichContent('body') !!}
</div>
@include('filament-tma::attachments', ['model' => $entry, 'collection' => \CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION, 'large' => $large ?? false])
