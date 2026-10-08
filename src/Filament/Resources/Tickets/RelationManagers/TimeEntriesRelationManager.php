<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers;

use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Le temps passé sur un ticket, dans le maître : une ligne par intervention, avec sa date (qui la range dans son
 * mois). Une saisie rattachée à une facture est verrouillée.
 */
class TimeEntriesRelationManager extends RelationManager
{
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
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                DatePicker::make('worked_on')->label(__('Le'))->required()->default(now())->maxDate(now()->addDay()),
                TextInput::make('duration')->label(__('Durée'))->required()->numeric()->minValue(0.01)->step(0.25),
                Select::make('unit')->label(__('En'))->options(Duration::units())->required()
                    ->default(config('filament-tma.time.display_unit', Duration::HOURS)),
            ]),
            Textarea::make('description')->label(__('Ce qui a été fait'))->rows(3)->columnSpanFull(),
            Toggle::make('billable')->label(__('Facturable'))->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('worked_on', 'desc')
            ->columns([
                TextColumn::make('worked_on')->label(__('Le'))->date('d/m/Y')->sortable(),
                TextColumn::make('actor.name')->label(__('Intervenant')),
                TextColumn::make('minutes')->label(__('Durée'))
                    ->formatStateUsing(fn (int $state): string => Duration::format($state))
                    ->summarize(Sum::make()->label(__('Total'))->formatStateUsing(fn ($state): string => Duration::format((int) $state))),
                TextColumn::make('description')->label(__('Description'))->limit(80)->wrap(),
                IconColumn::make('billable')->label(__('Facturable'))->boolean(),
                IconColumn::make('invoiced')->label(__('Facturée'))
                    ->state(fn (TimeEntry $record): bool => $record->isInvoiced())
                    ->boolean()->trueIcon('heroicon-m-lock-closed')->falseIcon('heroicon-m-minus'),
            ])
            ->headerActions([
                Action::make('logTime')
                    ->label(__('Saisir du temps'))
                    ->icon('heroicon-m-clock')
                    ->schema(fn (Schema $schema) => $this->form($schema))
                    ->visible(fn (): bool => app(TimeLog::class)->canLog($this->getTicket(), Context::current()))
                    ->action(function (array $data): void {
                        app(TimeLog::class)->add(
                            $this->getTicket(),
                            Duration::toMinutes($data['duration'], $data['unit']),
                            $data['worked_on'],
                            $data['description'] ?? null,
                            Context::current(),
                            (bool) ($data['billable'] ?? true),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (TimeEntry $record): bool => $record->isInvoiced())
                    ->mutateRecordDataUsing(fn (array $data): array => [...$data, 'duration' => round($data['minutes'] / 60, 2), 'unit' => Duration::HOURS])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...collect($data)->except(['duration', 'unit'])->all(),
                        'minutes' => Duration::toMinutes($data['duration'], $data['unit']),
                    ]),
                DeleteAction::make()->hidden(fn (TimeEntry $record): bool => $record->isInvoiced()),
            ]);
    }

    private function getTicket(): Ticket
    {
        /** @var Ticket */
        return $this->getOwnerRecord();
    }
}
