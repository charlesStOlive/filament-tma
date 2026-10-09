<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks;

use CharlesStOlive\FilamentStateFusionEnhanced\Tables\Filters\StateFusionSelectFilter;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\EditTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ListTasks;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskForm;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskInfolist;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Les tâches projet (CDC §8). Dans le maître : création, publication (tout de suite ou programmée), temps, fin.
 * Dans une application cliente : les tâches publiées pour elle, en lecture, avec leur chronologie où elle peut
 * commenter, noter, joindre des images et répondre.
 *
 * Droits (filament-permission-manager) : `tma.task.*`, avec `comment`, `note`, `share-note`, `time`, `sync` et la
 * famille `workflow` (`tma.task.workflow.publish`…).
 */
class TaskResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = ProjectTask::class;

    protected static ?string $slug = 'tma/tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $permissionPrefix = 'tma.task';

    /** @var array<int, string> */
    protected static array $specificPermissions = [TmaGate::COMMENT, TmaGate::NOTE, TmaGate::SHARE_NOTE, TmaGate::TIME, TmaGate::SYNC];

    /** @var array<string, string> */
    protected static array $permissionFamilies = [TmaGate::WORKFLOW_FAMILY => 'Passages de la tâche'];

    /** @var array<string, string> */
    protected static array $permissionLabels = [
        TmaGate::COMMENT => 'Commenter et répondre',
        TmaGate::NOTE => 'Lire et écrire les notes internes',
        TmaGate::SHARE_NOTE => 'Partager une note avec l’autre partie',
        TmaGate::TIME => 'Saisir et modifier du temps (maître)',
        TmaGate::SYNC => 'Relancer une synchronisation en erreur (maître)',
    ];

    /** @return array<string, string> */
    public static function permissionActions(): array
    {
        return collect(app(TaskWorkflow::class)->userMoves())
            ->mapWithKeys(fn (TaskMove $move): array => [TmaGate::workflowAction($move->key()) => $move->label()])
            ->all();
    }

    public static function getModelLabel(): string
    {
        return __('tâche projet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tâches projet');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function canCreate(): bool
    {
        return Tma::isMaster() && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        /** @var ProjectTask $record */
        return $record->fieldsEditableHere() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var ProjectTask $record */
        return Tma::isMaster() && ! $record->isShared() && parent::canDelete($record);
    }

    /** Le menu d'aide de la page : les tâches projet, leur chronologie, le temps passé. */
    public static function getDocumentation(): array|string
    {
        return ['tma.taches', 'tma.taches.publication', 'tma.chronologie', 'tma.temps'];
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('N°'))->searchable()->sortable(),
                TextColumn::make('title')->label(__('Titre'))->searchable()->limit(60)->wrap(),
                TextColumn::make('targetInstance.name')->label(__('Application'))->visible(fn (): bool => Tma::isMaster())->toggleable(),
                TextColumn::make('project')->label(__('Projet'))->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('state')->label(__('État'))->badge(),
                TextColumn::make('publish_at')->label(__('Publication prévue'))->dateTime('d/m/Y H:i')->placeholder('—')
                    ->visible(fn (): bool => Tma::isMaster())->toggleable(),
                TextColumn::make('ends_on')->label(__('Fin prévue'))->date('d/m/Y')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('time')->label(__('Temps'))
                    ->state(fn (ProjectTask $record): string => Duration::format(TimeTotals::for($record)['total_minutes'])),
                TextColumn::make('last_activity_at')->label(__('Dernière activité'))->since()->sortable(),
            ])
            ->filters([
                StateFusionSelectFilter::make('state')->label(__('État'))->multiple(),
                SelectFilter::make('target_instance_uid')->label(__('Application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [TimeEntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
