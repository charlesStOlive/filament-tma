<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tasks;

use CharlesStOlive\FilamentStateFusionEnhanced\Tables\Filters\StateFusionSelectFilter;
use CharlesStOlive\FilamentTma\Filament\Actions\TaskDeleteActions;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\EditTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ListTasks;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskForm;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Schemas\TaskInfolist;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskCancelled;
use CharlesStOlive\FilamentTma\States\Task\TaskDone;
use CharlesStOlive\FilamentTma\States\Task\TaskDraft;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Orthography;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use CharlesStOlive\FilamentTma\Workflow\TaskMove;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Les tâches projet (CDC §8), rangées par projet (`ProjectResource`). Dans le maître : création, publication (tout
 * de suite ou programmée), temps, fin, suppression (quel que soit l'état).
 * Dans une application cliente : les tâches publiées pour elle, en lecture, avec leur chronologie où elle peut
 * commenter, noter, joindre des images et répondre.
 *
 * Droits (filament-permission-manager) : `tma.task.*`, avec `comment`, `note`, `time`, `sync` et la
 * famille `workflow` (`tma.task.workflow.publish`…) ; avec filament-prism installé, `ai.orthography` (voir
 * `Support\Orthography`).
 */
class TaskResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = ProjectTask::class;

    protected static ?string $slug = 'tma/tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $permissionPrefix = 'tma.task';

    /** @var array<int, string> */
    protected static array $specificPermissions = [TmaGate::COMMENT, TmaGate::NOTE, TmaGate::TIME, TmaGate::SYNC, TmaGate::TIME_REPORT];

    /** @var array<string, string> */
    protected static array $permissionFamilies = [
        TmaGate::WORKFLOW_FAMILY => 'filament-tma::permissions.families.task_moves',
        Orthography::FAMILY => Orthography::FAMILY_LABEL,
    ];

    /** @var array<string, string> Des clés de traduction : filament-permission-manager les traduit à l'affichage. */
    protected static array $permissionLabels = [
        TmaGate::COMMENT => 'filament-tma::permissions.task.comment',
        TmaGate::NOTE => 'filament-tma::permissions.note',
        TmaGate::TIME => 'filament-tma::permissions.time',
        TmaGate::SYNC => 'filament-tma::permissions.sync',
        TmaGate::TIME_REPORT => 'filament-tma::permissions.task.time_report',
    ];

    /** @return array<string, string> */
    public static function permissionActions(): array
    {
        return [
            ...collect(app(TaskWorkflow::class)->userMoves())
                ->mapWithKeys(fn (TaskMove $move): array => [TmaGate::workflowAction($move->key()) => $move->label()])
                ->all(),
            ...Orthography::permissionActions(),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('filament-tma::tasks.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-tma::tasks.model.plural');
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

    /** Dans le maître seulement, quel que soit l'état de la tâche (`TaskWorkflow::delete()`). */
    public static function canDelete(Model $record): bool
    {
        return Tma::isMaster() && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return Tma::isMaster() && parent::canDeleteAny();
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

    /**
     * Une liste plutôt qu'un tableau (comme les scènes de filament-map) : chaque tâche sur une ligne souple (`Split`),
     * avec à gauche son numéro, son titre et le début de sa description (`Stack`), au milieu son état et sa prochaine
     * date (publication prévue, échéance, en retard, fin), à droite le temps passé et la dernière activité. Sous `md`,
     * les blocs s'empilent. Les tâches se rangent par projet ; dans le maître, on les supprime (une ou plusieurs).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('number')->label(__('filament-tma::common.number'))->searchable()->sortable()
                            ->fontFamily(FontFamily::Mono)->size(TextSize::ExtraSmall)->color('gray'),
                        TextColumn::make('title')->label(__('filament-tma::common.title'))->searchable()->sortable()
                            ->weight(FontWeight::SemiBold)->wrap(),
                        // Le début de la description, en texte (la colonne recevrait sinon le contenu riche).
                        TextColumn::make('excerpt')->label(__('filament-tma::common.description'))
                            ->state(fn (ProjectTask $record): ?string => filled($record->description) ? Str::limit(Html::toText($record->description), 140) : null)
                            ->color('gray')->size(TextSize::Small)->wrap(),
                    ])->space(1),
                    Stack::make([
                        TextColumn::make('state')->label(__('filament-tma::common.state'))->badge(),
                        TextColumn::make('due_on')->label(__('filament-tma::common.due_date'))->sortable()
                            ->state(fn (ProjectTask $record): ?string => self::nextDate($record)['text'] ?? null)
                            ->icon(fn (ProjectTask $record): ?string => self::nextDate($record)['icon'] ?? null)
                            ->color(fn (ProjectTask $record): string => self::nextDate($record)['color'] ?? 'gray')
                            ->size(TextSize::Small),
                        TextColumn::make('targetInstance.name')->label(__('filament-tma::common.application'))
                            ->icon('heroicon-m-computer-desktop')->color('gray')->size(TextSize::ExtraSmall)
                            ->visible(fn (): bool => Tma::isMaster()),
                    ])->space(1)->grow(false)
                        // Des largeurs fixes (ici et à droite) : d'une ligne à l'autre, l'état et sa date restent alignés.
                        ->extraAttributes(['style' => 'width: 15rem']),
                    Stack::make([
                        TextColumn::make('time')->label(__('filament-tma::common.time'))
                            ->state(fn (ProjectTask $record): string => Duration::format(TimeTotals::for($record)['total_minutes']))
                            ->icon('heroicon-m-clock')->weight(FontWeight::SemiBold),
                        TextColumn::make('last_activity_at')->label(__('filament-tma::common.last_activity'))->sortable()
                            ->since()->tooltip(fn (ProjectTask $record): string => Dates::format($record->last_activity_at))->color('gray')->size(TextSize::ExtraSmall),
                    ])->space(1)->grow(false)->extraAttributes(['style' => 'width: 8rem']),
                ])->from('md'),
            ])
            // Les tâches se rangent par projet.
            ->groups([
                Group::make('project.title')->label(__('filament-tma::common.project'))
                    ->getTitleFromRecordUsing(fn (ProjectTask $record): string => $record->project?->title ?? __('filament-tma::common.no_project')),
            ])
            ->defaultGroup('project.title')
            ->filters([
                SelectFilter::make('project_id')->label(__('filament-tma::common.project'))
                    ->options(fn (): array => Project::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable(),
                StateFusionSelectFilter::make('state')->label(__('filament-tma::common.state'))->multiple(),
                SelectFilter::make('target_instance_uid')->label(__('filament-tma::common.application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip(__('filament-tma::common.open')),
                TaskDeleteActions::make()->iconButton()->tooltip(__('filament-tma::common.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TaskDeleteActions::bulk()]),
            ]);
    }

    /**
     * La date qui compte pour une tâche, selon où elle en est : sa publication prévue (brouillon programmé), le jour de
     * sa fin ou de son annulation (sous l'état, qui le dit déjà), son échéance prévue, en rouge une fois dépassée.
     *
     * @return array{text: string, icon: string, color: string}|null
     */
    private static function nextDate(ProjectTask $task): ?array
    {
        return match (true) {
            $task->state instanceof TaskDraft && $task->publish_at !== null => ['text' => __('filament-tma::tasks.schedule.publication', ['date' => Dates::format($task->publish_at, 'd/m'), 'time' => Dates::format($task->publish_at, 'H:i')]), 'icon' => 'heroicon-m-paper-airplane', 'color' => 'info'],
            $task->state instanceof TaskDone && $task->done_at !== null => ['text' => __('filament-tma::tasks.schedule.on_date', ['date' => Dates::format($task->done_at, 'd/m/Y')]), 'icon' => 'heroicon-m-calendar', 'color' => 'gray'],
            $task->state instanceof TaskCancelled && $task->cancelled_at !== null => ['text' => __('filament-tma::tasks.schedule.on_date', ['date' => Dates::format($task->cancelled_at, 'd/m/Y')]), 'icon' => 'heroicon-m-calendar', 'color' => 'gray'],
            $task->due_on !== null && $task->due_on->endOfDay()->isPast() && ! $task->state instanceof TaskDone && ! $task->state instanceof TaskCancelled => ['text' => __('filament-tma::tasks.schedule.overdue', ['date' => $task->due_on->format('d/m')]), 'icon' => 'heroicon-m-exclamation-triangle', 'color' => 'danger'],
            $task->due_on !== null => ['text' => __('filament-tma::tasks.schedule.due', ['date' => $task->due_on->format('d/m')]), 'icon' => 'heroicon-m-calendar', 'color' => 'gray'],
            default => null,
        };
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
