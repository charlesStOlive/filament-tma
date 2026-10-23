<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Projects;

use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\CreateProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\EditProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ListProjects;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ViewProject;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\States\Task\TaskInProgress;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Resync;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Les projets : un titre, une application destinataire, une fourchette en jours, des participants, N tâches projet.
 * Le maître les crée et les modifie (dans un volet glissant, depuis la liste, la fiche du projet ou celle d'une de ses
 * tâches : `editAction()`) ; l'application cliente voit ceux qu'elle a reçus avec leurs tâches publiées. Le temps d'un
 * projet est la somme de celui de ses tâches (`Project::timeTotals()`), en jours et heures.
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

    /** Le nombre de tâches en cours nommées dans la liste, avant « et N autres ». */
    public const ONGOING_LINKS = 3;

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
     * Les champs d'un projet, repris par « Nouveau projet » dans le formulaire d'une tâche. L'application ne change plus
     * une fois des tâches dans le projet ; les participants sont ceux de cette application et le Support.
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
                ->live()
                ->disabled(fn (?Model $record): bool => $record instanceof Project && $record->tasks()->exists())
                ->helperText(__('Toutes les tâches du projet vont à cette application.'))
                ->hintAction(Help::action('tma.projets', __('Les projets'))),
            ...self::detailFields(
                fn (Get $get, ?Model $record): ?string => $get('target_instance_uid') ?? ($record instanceof Project ? $record->target_instance_uid : null),
                fn (?Model $record): ?Project => $record instanceof Project ? $record : null,
            ),
        ];
    }

    /**
     * Ce que l'on modifie d'un projet dans son volet : la description, la fourchette, les participants.
     *
     * @param  \Closure(Get, ?Model): ?string  $instanceUid  l'application du projet
     * @param  \Closure(?Model): ?Project  $project  le projet modifié (aucun à la création)
     * @return array<int, Component>
     */
    public static function detailFields(\Closure $instanceUid, \Closure $project): array
    {
        return [
            Textarea::make('description')->label(__('Description'))->rows(3)->columnSpanFull(),
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('estimate_min_days')->label(__('Fourchette : au moins'))
                    ->numeric()->minValue(0)->step(0.5)->suffix(__('j'))
                    ->helperText(__('En jours (une journée : :day) ; 0,5 pour une demi-journée.', ['day' => Duration::format(Duration::dayMinutes(), Duration::HOURS)]))
                    ->hintAction(Help::action('tma.projets.fourchette', __('La fourchette'))),
                TextInput::make('estimate_max_days')->label(__('au plus'))
                    ->numeric()->minValue(0)->step(0.5)->suffix(__('j'))
                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        if (filled($value) && filled($get('estimate_min_days')) && (float) $value < (float) $get('estimate_min_days')) {
                            $fail(__('Le haut de la fourchette ne peut pas être sous le bas.'));
                        }
                    }),
            ]),
            Select::make('member_keys')->label(__('Participants'))
                ->multiple()
                ->searchable()
                ->options(fn (Get $get, ?Model $record): array => self::memberOptions($instanceUid($get, $record), $project($record)))
                ->helperText(__('En plus des destinataires de ses tâches, qui y participent d’office.'))
                ->columnSpanFull(),
        ];
    }

    /**
     * Les participants possibles (`Support\Addressables` : le Support, les correspondants de l'application), et ceux
     * qui le sont déjà, même s'ils n'y sont plus.
     *
     * @return array<string, array<string, string>>
     */
    public static function memberOptions(?string $instanceUid, ?Project $project): array
    {
        $groups = Addressables::options($instanceUid);
        $known = array_merge(...array_values($groups ?: [[]]));

        foreach ($project?->members ?? [] as $actor) {
            if (! array_key_exists($key = Addressables::keyFor($actor), $known)) {
                $groups[__('Autres participants')][$key] = Addressables::label($actor);
            }
        }

        return $groups;
    }

    /** @param  array<string, mixed>  $data */
    public static function createProject(array $data): Project
    {
        $instance = Instance::query()->where('uid', $data['target_instance_uid'] ?? null)->firstOrFail();

        $project = Project::create([
            ...self::fields($data),
            'title' => $data['title'],
            'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type,
            'client_id' => $instance->client_id,
        ]);
        self::saveMembers($project, (array) ($data['member_keys'] ?? []));

        return $project;
    }

    /**
     * Un projet modifié (son application ne change que s'il n'a pas encore de tâche) ; un changement part chez le client
     * (`Project::pushToClient()`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function updateProject(Project $project, array $data): Project
    {
        $project->fill([
            ...self::fields($data),
            ...Arr::only($data, ['title']),
            ...(filled($data['target_instance_uid'] ?? null) && ! $project->tasks()->exists() ? ['target_instance_uid' => $data['target_instance_uid']] : []),
        ])->save();

        if (array_key_exists('member_keys', $data)) {
            self::saveMembers($project, (array) $data['member_keys']);
        }

        return $project;
    }

    /** @return array<string, mixed> ce qu'un formulaire de projet remplit (avec les participants par leur clé) */
    public static function formData(Project $project): array
    {
        return [
            ...$project->only(['title', 'target_instance_uid', 'description', 'estimate_min_days', 'estimate_max_days']),
            'member_keys' => $project->members->map(fn (Actor $actor): string => Addressables::keyFor($actor))->values()->all(),
        ];
    }

    /** @param  array<int, string>  $keys  des clés d'`Addressables` */
    private static function saveMembers(Project $project, array $keys): void
    {
        $project->syncMembers(collect($keys)
            ->map(fn (string $key): ?Actor => Addressables::resolve($key, $project->target_instance_uid))
            ->filter()
            ->map(fn (Actor $actor): int => (int) $actor->getKey())
            ->all());
    }

    /** @return array<string, mixed> */
    private static function fields(array $data): array
    {
        $days = fn (mixed $value): ?float => filled($value) ? round((float) str_replace(',', '.', (string) $value), 2) : null;

        return [
            ...Arr::only($data, ['description']),
            ...(array_key_exists('estimate_min_days', $data) ? ['estimate_min_days' => $days($data['estimate_min_days'])] : []),
            ...(array_key_exists('estimate_max_days', $data) ? ['estimate_max_days' => $days($data['estimate_max_days'])] : []),
        ];
    }

    /**
     * « Modifier le projet », dans un volet glissant : depuis la liste et la fiche d'un projet (l'enregistrement est le
     * projet) ou depuis la fiche d'une tâche (son projet). Maître seulement, avec le droit de modifier les projets.
     */
    public static function editAction(): Action
    {
        $project = fn (Model $record): ?Project => $record instanceof ProjectTask ? $record->project : ($record instanceof Project ? $record : null);

        return Action::make('editProject')
            ->label(__('Modifier le projet'))
            ->icon('heroicon-m-pencil-square')
            ->slideOver()
            ->modalWidth('lg')
            ->visible(fn (Model $record): bool => ($found = $project($record)) !== null && static::canEdit($found))
            ->modalHeading(fn (Model $record): string => $project($record)->title)
            ->modalDescription(fn (Model $record): ?string => Tma::isMaster() ? $project($record)->targetInstance?->name : null)
            ->fillForm(fn (Model $record): array => self::formData($project($record)))
            ->schema(fn (Model $record): array => [
                TextInput::make('title')->label(__('Titre'))->required()->maxLength(255),
                ...self::detailFields(fn (): string => $project($record)->target_instance_uid, fn (): ?Project => $project($record)),
            ])
            ->modalSubmitActionLabel(__('Enregistrer'))
            ->action(function (Model $record, array $data) use ($project): void {
                self::updateProject($project($record), $data);
                Notification::make()->success()->title(__('Projet enregistré'))->send();
            });
    }

    /**
     * Maître : « Renvoyer à l'application » — le projet (sa fourchette, ses participants) et ses tâches publiées
     * repartent, par la file. Pour qui peut relancer une synchronisation (`tma.task.sync`).
     */
    public static function resyncAction(): Action
    {
        return Action::make('resync')
            ->label(__('Renvoyer à l’application'))
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->visible(fn (Project $record): bool => Tma::isMaster() && TmaGate::allows(auth()->user(), TmaGate::SYNC, new ProjectTask))
            ->action(function (Project $record): void {
                Resync::pushProjects([$record]);
                $sent = Resync::push(Resync::subjects(project: $record));

                Notification::make()->success()
                    ->title(__('Envoi demandé'))
                    ->body($sent > 0
                        ? trans_choice('Le projet repart, avec :n tâche publiée.|Le projet repart, avec ses :n tâches publiées.', $sent, ['n' => $sent])
                        : __('Le projet repart (il n’a pas de tâche publiée).'))
                    ->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                TextEntry::make('targetInstance.name')->label(__('Application'))->visible(fn (): bool => Tma::isMaster()),
                TextEntry::make('tasks_count')->label(__('Tâches'))
                    ->state(fn (Project $record): string => __(':done / :total terminées', [
                        'done' => $record->tasks()->where('state', 'done')->count(),
                        'total' => $record->tasks()->count(),
                    ])),
                TextEntry::make('estimate')->label(__('Fourchette'))
                    ->state(fn (Project $record): ?string => $record->estimateLabel())
                    ->placeholder(__('Pas de fourchette'))
                    ->hintAction(Help::action('tma.projets.fourchette', __('La fourchette'))),
                TextEntry::make('time')->label(__('Temps passé'))
                    ->state(fn (Project $record): string => Duration::format($record->timeTotals()['total_minutes']))
                    ->color(fn (Project $record): ?string => $record->isOverEstimate($record->timeTotals()['total_minutes']) ? 'danger' : null)
                    ->weight(FontWeight::SemiBold)
                    ->helperText(__('La somme du temps de ses tâches.'))
                    ->hintAction(Help::action('tma.projets', __('Le temps d’un projet'))),
                TextEntry::make('participants')->label(__('Participants'))
                    ->state(fn (Project $record): array => $record->participants()->map(fn (Actor $actor): string => Addressables::label($actor))->all())
                    ->badge()->color('gray')->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('description')->label(__('Description'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * Une liste plutôt qu'un tableau, comme celle des tâches : à gauche le projet, sa description et ses participants ;
     * au milieu sa fourchette, son temps (en rouge au-delà de la fourchette) et ses tâches terminées ; à droite ses tâches
     * en cours, chacune un lien. Sous `md`, les blocs s'empilent. Une ligne ne mène pas d'elle-même au projet (elle
     * contient des liens) : son titre, si.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['targetInstance', 'members', 'tasks.recipients.actor'])
                ->withCount([
                    'tasks',
                    'tasks as done_tasks_count' => fn (Builder $q): Builder => $q->where('state', 'done'),
                ]))
            ->recordUrl(null)
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('title')->label(__('Titre'))->searchable()->sortable()
                            ->weight(FontWeight::SemiBold)->wrap()
                            ->url(fn (Project $record): ?string => static::canView($record) ? static::getUrl('view', ['record' => $record]) : null),
                        TextColumn::make('excerpt')->label(__('Description'))
                            ->state(fn (Project $record): ?string => filled($record->description) ? Str::limit($record->description, 140) : null)
                            ->color('gray')->size(TextSize::Small)->wrap(),
                        TextColumn::make('participants')->label(__('Participants'))
                            ->state(fn (Project $record): array => $record->participants()->map(fn (Actor $actor): string => Addressables::label($actor))->all())
                            ->badge()->color('gray')->size(TextSize::ExtraSmall)
                            ->limitList(6)->expandableLimitedList(),
                    ])->space(1),
                    Stack::make([
                        TextColumn::make('estimate')->label(__('Fourchette'))
                            ->state(fn (Project $record): string => $record->estimateLabel() ?? __('Pas de fourchette'))
                            ->tooltip(__('Fourchette'))
                            ->icon('heroicon-m-scale')
                            ->color(fn (Project $record): string => $record->estimateLabel() === null ? 'gray' : 'primary'),
                        TextColumn::make('time')->label(__('Temps'))
                            ->state(fn (Project $record): string => Duration::format($record->timeTotals()['total_minutes']))
                            ->tooltip(__('Temps passé'))
                            ->icon('heroicon-m-clock')->weight(FontWeight::SemiBold)
                            ->color(fn (Project $record): ?string => $record->isOverEstimate($record->timeTotals()['total_minutes']) ? 'danger' : null),
                        TextColumn::make('tasks_count')->label(__('Tâches'))->sortable()
                            ->formatStateUsing(fn (Project $record): string => __(':done / :total terminées', ['done' => $record->done_tasks_count, 'total' => $record->tasks_count]))
                            ->color('gray')->size(TextSize::ExtraSmall),
                        TextColumn::make('targetInstance.name')->label(__('Application'))
                            ->icon('heroicon-m-computer-desktop')->color('gray')->size(TextSize::ExtraSmall)
                            ->visible(fn (): bool => Tma::isMaster()),
                    ])->space(1)->grow(false)
                        // Des largeurs fixes : d'une ligne à l'autre, la fourchette et les tâches restent alignées.
                        ->extraAttributes(['style' => 'width: 12rem']),
                    Stack::make([
                        TextColumn::make('ongoing')->label(__('En cours'))
                            ->state(fn (Project $record): HtmlString => self::ongoingLinks($record))
                            ->html()
                            ->size(TextSize::Small),
                    ])->grow(false)->extraAttributes(['style' => 'width: 18rem']),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('target_instance_uid')->label(__('Application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip(__('Ouvrir')),
                static::editAction()->iconButton()->tooltip(__('Modifier le projet')),
            ]);
    }

    /**
     * Les tâches en cours d'un projet : leur nombre, puis chacune en lien (les `ONGOING_LINKS` plus récemment actives),
     * et « et N autres », qui mène à la liste des tâches du projet en cours.
     */
    public static function ongoingLinks(Project $project): HtmlString
    {
        $ongoing = $project->tasks
            ->filter(fn (ProjectTask $task): bool => $task->state instanceof TaskInProgress)
            ->sortByDesc('last_activity_at')
            ->values();

        if ($ongoing->isEmpty()) {
            return new HtmlString('<span class="fi-tma-entry-instance">'.e(__('Aucune tâche en cours')).'</span>');
        }

        $listUrl = TaskResource::getUrl('index', ['filters' => [
            'project_id' => ['value' => $project->getKey()],
            'state' => ['values' => [TaskInProgress::getMorphClass()]],
        ]]);

        $lines = $ongoing->take(self::ONGOING_LINKS)->map(fn (ProjectTask $task): string => TaskResource::canView($task)
            ? '<a href="'.e(TaskResource::getUrl('view', ['record' => $task])).'" class="fi-link">'.e($task->number.' · '.Str::limit($task->title, 40)).'</a>'
            : e($task->number.' · '.Str::limit($task->title, 40)));

        if ($ongoing->count() > self::ONGOING_LINKS) {
            $lines->push('<a href="'.e($listUrl).'" class="fi-link">'.e(trans_choice('et :n autre|et :n autres', $ongoing->count() - self::ONGOING_LINKS, ['n' => $ongoing->count() - self::ONGOING_LINKS])).'</a>');
        }

        return new HtmlString('<a href="'.e($listUrl).'" class="fi-link font-semibold">'.e(trans_choice(':n tâche en cours|:n tâches en cours', $ongoing->count(), ['n' => $ongoing->count()])).'</a><br>'.$lines->implode('<br>'));
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
