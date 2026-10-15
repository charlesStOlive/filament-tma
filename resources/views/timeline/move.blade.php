@php
    /**
     * Un changement d'état, dans l'en-tête du bloc qui l'accompagne : « — Demander des précisions [En cours] → [En attente
     * du demandeur] » ; « par Ada Lovelace » quand ce n'est pas l'auteur du bloc qui l'a fait.
     *
     * @var \CharlesStOlive\FilamentTma\Models\StateTransition $transition
     * @var \Illuminate\Support\Collection $moveLabels
     * @var \Illuminate\Support\Collection $states
     * @var \CharlesStOlive\FilamentTma\Models\Actor|null $author  l'auteur du bloc
     */
    $actor = $transition->actor;
    $byOther = $author !== null && ! $actor?->is($author);
@endphp
<span class="fi-tma-entry-move">
    — {{ $moveLabels[$transition->move] ?? $transition->move }}
    @if ($byOther)
        <span class="fi-tma-entry-instance">{{ __('par :name', ['name' => $actor?->name ?? __('Système')]) }}</span>
    @endif
    @if ($transition->from_state)
        <x-filament::badge :color="$states[$transition->from_state]?->getColor() ?? 'gray'" size="sm">{{ $states[$transition->from_state]?->getLabel() ?? $transition->from_state }}</x-filament::badge>
        →
    @endif
    <x-filament::badge :color="$states[$transition->to_state]?->getColor() ?? 'gray'" size="sm">{{ $states[$transition->to_state]?->getLabel() ?? $transition->to_state }}</x-filament::badge>
</span>
