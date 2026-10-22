<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TimeEntryForm;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Le temps passé sur un ticket ou une tâche projet, dans le maître, pour qui peut le saisir : les totaux, puis une ligne
 * par intervention, avec son jour (qui la range dans son mois), et au besoin le message ou la note sur lequel elle porte.
 * Une saisie rattachée à une facture est verrouillée. Les autres voient les totaux seuls (`HasInfoPane::timeBlock()`). La liste se range dans le volet de droite
 * de la fiche (`HasInfoPane`).
 */
class TimeEntriesRelationManager extends RelationManager
{
    /** Envoyé à la page à chaque saisie : l'historique montre le nouveau message, et le temps de chaque message. */
    public const TIMELINE_CHANGED = 'tma-timeline-changed';

    protected static string $relationship = 'timeEntries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Temps passé');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Tma::isMaster() && TmaGate::allows(auth()->user(), TmaGate::TIME, $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(TimeEntryForm::components(fn (): Trackable => $this->getTicket()));
    }

    public function table(Table $table): Table
    {
        // Un seul bloc pour le temps, fait pour le volet de droite de la fiche (voir `HasInfoPane`) : les totaux en tête,
        // puis une ligne par saisie (qui, le jour, combien), et dessous, s'il y en a, le message ou la note sur lequel elle porte.
        return $table
            ->description(fn (): string => self::totals($this->getTicket()))
            ->defaultSort('worked_on', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->paginated([10])
            ->emptyStateHeading(__('Pas encore de temps saisi'))
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                Split::make([
                    TextColumn::make('actor.name')->label(__('Intervenant'))->weight(FontWeight::Medium)->limit(24),
                    TextColumn::make('worked_on')->label(__('Le'))->date('d/m')->color('gray')->grow(false),
                    TextColumn::make('minutes')->label(__('Durée'))
                        ->formatStateUsing(fn (int $state): string => Duration::format($state))
                        ->weight(FontWeight::SemiBold)->alignEnd()->grow(false),
                ]),
                TextColumn::make('details')->label(__('Détail'))
                    ->state(fn (TimeEntry $record): ?string => collect([
                        $record->entry !== null ? __('Sur : :entry', ['entry' => TimeEntryForm::entryLabel($record->entry, short: true)]) : null,
                        $record->billable ? null : __('Non facturable'),
                        $record->isInvoiced() ? __('Facturée') : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->wrap()->color('gray')->size(TextSize::ExtraSmall),
            ])
            ->headerActions([
                Action::make('logTime')
                    ->label(__('Saisir du temps'))
                    ->icon('heroicon-m-plus')
                    ->size(Size::Small)
                    ->schema(fn (Schema $schema) => $this->form($schema))
                    ->visible(fn (): bool => app(TimeLog::class)->canLog($this->getTicket(), Context::current()))
                    ->action(function (array $data): void {
                        $ticket = $this->getTicket();
                        $message = TimeEntryForm::message($data);
                        $minutes = TimeEntryForm::minutes($data);
                        $billable = (bool) ($data['billable'] ?? true);

                        $message !== null
                            ? app(TimeLog::class)->addWithMessage($ticket, $minutes, $data['worked_on'], $message, Context::current(), $billable)
                            : app(TimeLog::class)->add($ticket, $minutes, $data['worked_on'], Context::current(), $billable,
                                filled($data['entry_id'] ?? null) ? $ticket->entries()->find($data['entry_id']) : null);

                        $this->dispatch(self::TIMELINE_CHANGED);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Modifier'))
                    ->hidden(fn (TimeEntry $record): bool => $record->isInvoiced())
                    ->mutateRecordDataUsing(fn (array $data): array => [...$data, ...TimeEntryForm::fill((int) $data['minutes'])])
                    ->mutateDataUsing(function (array $data): array {
                        $ticket = $this->getTicket();
                        $message = TimeEntryForm::message($data);

                        // Un nouveau message écrit en modifiant la saisie : elle porte désormais dessus.
                        if ($message !== null) {
                            $data['entry_id'] = app(Timeline::class)->add($ticket, EntryType::Comment, $message, Context::current())->getKey();
                        }

                        TimeLog::assertEntryOf($ticket, filled($data['entry_id'] ?? null) ? Entry::query()->find($data['entry_id']) : null);

                        return [
                            ...collect($data)->except([TimeEntryForm::DAYS, TimeEntryForm::HOURS, TimeEntryForm::MINUTES, 'message'])->all(),
                            'entry_id' => filled($data['entry_id'] ?? null) ? $data['entry_id'] : null,
                            'minutes' => TimeEntryForm::minutes($data),
                        ];
                    })
                    ->after(fn () => $this->dispatch(self::TIMELINE_CHANGED)),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Supprimer'))
                    ->hidden(fn (TimeEntry $record): bool => $record->isInvoiced())
                    ->after(fn () => $this->dispatch(self::TIMELINE_CHANGED)),
            ]);
    }

    /**
     * Les totaux, sur une ligne : « Cette tâche : 6 h 01 · Tout le projet : 12 h » ; « Ce ticket : 2 h ».
     */
    public static function totals(Trackable $subject): string
    {
        $own = Duration::format(TimeTotals::for($subject)['total_minutes']);

        if (! $subject instanceof ProjectTask) {
            return __('Ce ticket : :time', ['time' => $own]);
        }

        return $subject->project === null
            ? __('Cette tâche : :time', ['time' => $own])
            : __('Cette tâche : :time · Tout le projet : :project', ['time' => $own, 'project' => Duration::format($subject->project->timeTotals()['total_minutes'])]);
    }

    private function getTicket(): Trackable
    {
        /** @var Trackable */
        return $this->getOwnerRecord();
    }
}
