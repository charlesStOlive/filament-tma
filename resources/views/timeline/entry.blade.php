@php
    /** @var \CharlesStOlive\FilamentTma\Models\Entry $entry */
@endphp
<header class="fi-tma-entry-header">
    <x-filament::badge :color="$entry->type->getColor()" :icon="$entry->type->getIcon()" size="sm">{{ $entry->type->getLabel() }}</x-filament::badge>
    @if ($entry->type === \CharlesStOlive\FilamentTma\Enums\EntryType::Note && $entry->isShared())
        <x-filament::badge color="info" size="sm">{{ __('Partagée') }}</x-filament::badge>
    @endif
    <strong>{{ $entry->author?->name }}</strong>
    @if ($team = $entry->author?->team())
        <x-filament::badge color="gray" size="sm" class="fi-tma-entry-instance">{{ $team }}</x-filament::badge>
    @endif
    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
</header>
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    {!! $entry->renderRichContent('body') !!}
</div>
@include('filament-tma::attachments', ['model' => $entry, 'collection' => \CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION])
