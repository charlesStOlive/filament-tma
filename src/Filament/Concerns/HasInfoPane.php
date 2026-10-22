<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Filament\Actions\TimelineActions;
use CharlesStOlive\FilamentTma\Filament\Livewire\AgentTimeEntries;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentUi\Split\HasSidePane;
use CharlesStOlive\FilamentUi\Split\SidePane;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * La fiche d'un ticket ou d'une tâche en deux volets (`HasSidePane` de filament-ui) : à gauche, l'historique et le
 * nouveau message ; à droite, un volet toujours ouvert, en deux onglets : le suivi (et, dans le maître, le temps
 * passé), puis les notes. Le volet prend 20 % de la largeur ; on la règle en glissant la barre (le navigateur s'en
 * souvient), un double clic la remet.
 *
 * La page dit ce que contient le premier onglet (`sideComponents()`) et où s'y range le temps passé, qui était une
 * liste sous la fiche. L'onglet des notes les montre toutes en aperçu ; un clic ouvre la note dans sa fenêtre
 * (`HasNotes`).
 */
trait HasInfoPane
{
    use HasSidePane;

    /**
     * Le premier onglet : où en est l'objet ; la page y range le bloc du temps passé (`$time`, voir `timeBlock()`) où elle
     * veut.
     *
     * @return array<int, Component>
     */
    abstract protected function sideComponents(Component $time): array;

    /**
     * Le temps passé, en un seul bloc : dans le maître, pour qui peut saisir du temps, les totaux et le détail des
     * saisies (`TimeEntriesRelationManager`) ; un membre du Support, dans une application cliente, le même détail, lu
     * dans le maître (`AgentTimeEntries`) ; pour tous les autres, les totaux seuls, en lecture.
     */
    protected function timeBlock(): Component
    {
        $canLog = fn (Model $record): bool => TimeEntriesRelationManager::canViewForRecord($record, static::class);
        $isAgent = fn (Model $record): bool => $record instanceof Trackable && Context::current()->forSubject($record)->isAgent();

        return Group::make([
            Livewire::make(TimeEntriesRelationManager::class, fn (): array => ['ownerRecord' => $this->getRecord(), 'pageClass' => static::class])
                ->key('time-entries')
                ->visible($canLog),
            // Un membre du Support dans une application cliente : le détail, lu dans le maître.
            Livewire::make(AgentTimeEntries::class, fn (): array => ['record' => $this->getRecord()])
                ->key('agent-time-entries')
                ->visible(fn (Model $record): bool => ! $canLog($record) && $isAgent($record)),
            Section::make(__('Temps passé'))->key('time')->compact()->columnSpanFull()
                ->visible(fn (Model $record): bool => ! $canLog($record) && ! $isAgent($record))
                ->headerActions([TimelineActions::agentTime()->size(Size::Small)])
                ->schema([
                    TextEntry::make('time_totals')->hiddenLabel()
                        ->state(fn (Trackable $record): string => TimeEntriesRelationManager::totals($record)),
                ]),
        ])->columnSpanFull();
    }

    /**
     * La liste du temps passé (un composant à part, dans le volet) a écrit un message, ou changé le temps d'un message
     * ou d'une note : l'historique se relit.
     */
    #[On(TimeEntriesRelationManager::TIMELINE_CHANGED)]
    public function refreshTimeline(): void
    {
        $this->getRecord()->refresh();
    }

    /** Le premier onglet du volet : « Suivi » pour un ticket. */
    protected function sideTabLabel(): string
    {
        return __('Suivi');
    }

    protected function sideTabIcon(): string
    {
        return 'heroicon-m-signal';
    }

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

    /**
     * Le volet : l'onglet du suivi, avec le temps passé (maître seulement, voir `TimeEntriesRelationManager`), et
     * l'onglet des notes.
     */
    public function sideInfolist(Schema $schema): Schema
    {
        return $schema->record($this->getRecord())->components([
            Tabs::make('side')
                ->persistTab()
                ->id('tma-side')
                ->contained(false)
                ->tabs([
                    Tab::make($this->sideTabLabel())
                        ->icon($this->sideTabIcon())
                        ->schema($this->sideComponents($this->timeBlock())),
                    Tab::make(__('Notes'))
                        ->icon('heroicon-m-pencil-square')
                        ->badge(fn (Trackable $record): ?int => $record->entries()->notes()->visibleTo(auth()->user())->whereNull('archived_at')->count() ?: null)
                        ->badgeColor('warning')
                        ->visible(fn (): bool => Timeline::notesEnabled())
                        ->schema([
                            ViewEntry::make('notes')->hiddenLabel()->view('filament-tma::notes.list'),
                        ]),
                ]),
        ]);
    }

    /** Le temps passé est dans le volet : plus de liste sous la fiche. */
    public function getRelationManagers(): array
    {
        return [];
    }
}
