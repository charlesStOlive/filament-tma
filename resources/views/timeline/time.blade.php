@php
    use CharlesStOlive\FilamentTma\Support\Duration;
    use CharlesStOlive\FilamentTma\Support\Tma;
    use CharlesStOlive\FilamentTma\Support\TmaGate;

    /**
     * Le temps saisi sur un message ou une note (dans le maître, pour qui voit le temps) : « 1 h 30 ».
     *
     * @var \CharlesStOlive\FilamentTma\Models\Entry $entry
     */
    $minutes = Tma::isMaster() && $entry->subject !== null && TmaGate::allows(auth()->user(), TmaGate::TIME, $entry->subject)
        ? (int) $entry->timeEntries()->sum('minutes')
        : 0;
@endphp
@if ($minutes > 0)
    <x-filament::badge color="gray" size="sm" icon="heroicon-m-clock" :tooltip="__('filament-tma::timeline.time.on_message')">{{ Duration::format($minutes, Duration::AUTO) }}</x-filament::badge>
@endif
