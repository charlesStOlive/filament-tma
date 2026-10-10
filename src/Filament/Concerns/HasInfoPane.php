<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentUi\Split\HasSidePane;
use CharlesStOlive\FilamentUi\Split\SidePane;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * La fiche d'un ticket ou d'une tâche en deux volets (`HasSidePane` de filament-ui) : à gauche, la demande et la
 * chronologie ; à droite, un volet toujours ouvert avec le suivi et, dans le maître, le temps passé. Le volet prend
 * 20 % de la largeur ; on la règle en glissant la barre (le navigateur s'en souvient), un double clic la remet.
 *
 * La page dit ce que contient le suivi (`sideComponents()`) ; le temps passé, qui était une liste sous la fiche, vient
 * se ranger dessous.
 */
trait HasInfoPane
{
    use HasSidePane;

    /**
     * Le suivi : où en est l'objet, et qui doit agir.
     *
     * @return array<int, Component>
     */
    abstract protected function sideComponents(): array;

    /** @return array<string, SidePane> */
    protected function getSidePanes(): array
    {
        return [
            'infos' => SidePane::make('infos')
                ->label(__('Suivi'))
                ->closable(false)
                ->hiddenHeader()
                ->schema([EmbeddedSchema::make('sideInfolist')]),
        ];
    }

    protected function getDefaultSidePane(): ?string
    {
        return 'infos';
    }

    protected function getSidePaneWidth(): int
    {
        return 20;
    }

    protected function getSidePaneMinWidth(): int
    {
        return 15;
    }

    protected function getSidePaneMaxWidth(): int
    {
        return 40;
    }

    /** Le volet : le suivi, puis le temps passé (maître seulement, voir `TimeEntriesRelationManager`). */
    public function sideInfolist(Schema $schema): Schema
    {
        return $schema->record($this->getRecord())->components([
            ...$this->sideComponents(),
            Livewire::make(TimeEntriesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class])
                ->key('time-entries')
                ->visible(fn (Model $record): bool => TimeEntriesRelationManager::canViewForRecord($record, static::class)),
        ]);
    }

    /** Le temps passé est dans le volet : plus de liste sous la fiche. */
    public function getRelationManagers(): array
    {
        return [];
    }
}
