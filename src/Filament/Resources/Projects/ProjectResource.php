<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\CreateProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\EditProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ListProjects;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ViewProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Les projets : un titre, une application destinataire, N tâches projet. Le maître les crée et les modifie ;
 * l'application cliente voit ceux qu'elle a reçus avec leurs tâches publiées. Le temps d'un projet est la somme de
 * celui de ses tâches (`Project::timeTotals()`).
 *
 * Droits (filament-permission-manager) : `tma.project.*`.
 */
class ProjectResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'tma/projects';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $permissionPrefix = 'tma.project';

    public static function getModelLabel(): string
    {
        return __('projet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projets');
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
        return Tma::isMaster() && parent::canEdit($record);
    }

    /** Un projet vide seulement : ses tâches gardent leur projet. */
    public static function canDelete(Model $record): bool
    {
        /** @var Project $record */
        return Tma::isMaster() && ! $record->tasks()->exists() && parent::canDelete($record);
    }

    /** Le menu d'aide de la page : les projets, leurs tâches, le temps passé. */
    public static function getDocumentation(): array|string
    {
        return ['tma.projets', 'tma.taches', 'tma.temps'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::formFields());
    }

    /**
     * Les champs d'un projet, repris par « Nouveau projet » dans le formulaire d'une tâche.
     *
     * @return array<int, Component>
     */
    public static function formFields(): array
    {
        return [
            TextInput::make('title')->label(__('Titre'))->required()->maxLength(255)->columnSpanFull(),
            Select::make('target_instance_uid')->label(__('Application destinataire'))
                ->options(fn (): array => Instance::query()->active()->orderBy('name')->pluck('name', 'uid')->all())
                ->required()
                ->disabled(fn (?Project $record): bool => $record?->tasks()->exists() ?? false)
                ->helperText(__('Toutes les tâches du projet vont à cette application.'))
                ->hintAction(Help::action('tma.projets', __('Les projets'))),
            Textarea::make('description')->label(__('Description'))->rows(3)->columnSpanFull(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function createProject(array $data): Project
    {
        $instance = Instance::query()->where('uid', $data['target_instance_uid'] ?? null)->firstOrFail();

        return Project::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(3)->schema([
                TextEntry::make('targetInstance.name')->label(__('Application'))->visible(fn (): bool => Tma::isMaster()),
                TextEntry::make('tasks_count')->label(__('Tâches'))
                    ->state(fn (Project $record): string => __(':done / :total terminées', [
                        'done' => $record->tasks()->where('state', 'done')->count(),
                        'total' => $record->tasks()->count(),
                    ])),
                TextEntry::make('time')->label(__('Temps passé'))
                    ->state(fn (Project $record): string => Duration::format($record->timeTotals()['total_minutes']))
                    ->helperText(__('La somme du temps de ses tâches.'))
                    ->hintAction(Help::action('tma.projets', __('Le temps d’un projet'))),
                TextEntry::make('description')->label(__('Description'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $q): Builder => $q->where('state', 'done'),
            ]))
            ->columns([
                TextColumn::make('title')->label(__('Titre'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (Project $record): ?string => $record->description),
                TextColumn::make('targetInstance.name')->label(__('Application'))->visible(fn (): bool => Tma::isMaster())->toggleable(),
                TextColumn::make('tasks_count')->label(__('Tâches'))->sortable()
                    ->formatStateUsing(fn (Project $record): string => __(':done / :total terminées', ['done' => $record->done_tasks_count, 'total' => $record->tasks_count])),
                TextColumn::make('time')->label(__('Temps'))
                    ->state(fn (Project $record): string => Duration::format($record->timeTotals()['total_minutes'])),
                TextColumn::make('updated_at')->label(__('Modifié'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('target_instance_uid')->label(__('Application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [TasksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
