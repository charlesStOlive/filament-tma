<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects\RelationManagers;

use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Les tâches d'un projet, leur temps ; une ligne ouvre la tâche, « Nouvelle tâche » en crée une dans
 * ce projet (maître).
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-tma::projects.tasks.heading');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->recordUrl(fn (ProjectTask $record): ?string => TaskResource::canView($record) ? TaskResource::getUrl('view', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('number')->label(__('filament-tma::common.number'))->sortable(),
                TextColumn::make('title')->label(__('filament-tma::common.title'))->searchable()->wrap(),
                TextColumn::make('state')->label(__('filament-tma::common.state'))->badge(),
                TextColumn::make('starts_on')->label(__('filament-tma::common.start'))->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('due_on')->label(__('filament-tma::common.due_date'))->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('time')->label(__('filament-tma::common.time'))
                    ->state(fn (ProjectTask $record): string => Duration::format(TimeTotals::for($record)['total_minutes'], Duration::AUTO)),
            ])
            ->headerActions([
                Action::make('newTask')
                    ->label(__('filament-tma::projects.tasks.create'))
                    ->icon('heroicon-m-plus')
                    ->url(fn (): string => TaskResource::getUrl('create', ['project' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn (): bool => TaskResource::canCreate()),
            ]);
    }
}
