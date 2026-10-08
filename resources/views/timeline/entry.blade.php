@php
    /** @var \CharlesStOlive\FilamentTma\Models\Entry $entry */
@endphp
<header class="fi-tma-entry-header">
    <x-filament::badge :color="$entry->type->getColor()" :icon="$entry->type->getIcon()" size="sm">{{ $entry->type->getLabel() }}</x-filament::badge>
    @if ($entry->type === \CharlesStOlive\FilamentTma\Enums\EntryType::Note)
        <x-filament::badge :color="$entry->isShared() ? 'info' : 'gray'" size="sm">{{ $entry->isShared() ? __('Partagée') : __('Interne') }}</x-filament::badge>
    @endif
    <strong>{{ $entry->author?->name }}</strong>
    @unless ($entry->author?->isLocal())
        <span class="fi-tma-entry-instance">{{ __('(autre application)') }}</span>
    @endunless
    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
</header>
<div class="fi-tma-entry-body prose dark:prose-invert max-w-none">
    {!! $entry->renderRichContent('body') !!}
</div>
@include('filament-tma::attachments', ['model' => $entry, 'collection' => \CharlesStOlive\FilamentTma\Models\Entry::ATTACHMENTS_COLLECTION])
