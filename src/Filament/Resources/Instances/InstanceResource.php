<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\EnvFile;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Resync;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\InstanceClient;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * « Applications clientes » (maître seulement) : les installations du plugin en mode client, leur adresse, leur
 * client, le compte par lequel elles appellent le maître et le jeton par lequel le maître les appelle.
 */
class InstanceResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Instance::class;

    protected static ?string $slug = 'tma/instances';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $permissionPrefix = 'tma.instance';

    public static function getModelLabel(): string
    {
        return __('filament-tma::instances.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-tma::instances.model.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Tma::isMaster() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return Tma::isMaster() && parent::canAccess();
    }

    /** Le menu d'aide de la page : relier une application cliente, et la synchronisation. */
    public static function getDocumentation(): array|string
    {
        return ['tma.demarrer', 'tma.demarrer.identifiant', 'tma.demarrer.adresse', 'tma.demarrer.jetons', 'tma.synchronisation.erreurs'];
    }

    public static function form(Schema $schema): Schema
    {
        $clientModel = config('filament-tma.models.client');

        return $schema->components([
            Section::make(__('filament-tma::common.application'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('filament-tma::instances.fields.name'))->required()->maxLength(255),
                TextInput::make('short_name')->label(__('filament-tma::instances.fields.short_name'))->maxLength(30)
                    ->placeholder('EDM')
                    ->helperText(__('filament-tma::instances.fields.short_name_help'))
                    ->hintAction(Help::action('tma.comprendre.vocabulaire', __('filament-tma::instances.help.wording'))),
                TextInput::make('uid')->label(__('filament-tma::instances.fields.uid'))
                    ->required()->length(26)->unique(ignoreRecord: true)
                    ->default(fn (): string => self::newUid())
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state)))
                    ->disabled(fn (?Instance $record): bool => $record !== null && $record->hasTickets())
                    ->suffixAction(
                        Action::make('generateUid')
                            ->icon('heroicon-m-arrow-path')
                            ->tooltip(__('filament-tma::instances.fields.uid_regenerate'))
                            ->visible(fn (?Instance $record): bool => $record === null || ! $record->hasTickets())
                            ->action(fn ($set) => $set('uid', self::newUid())),
                    )
                    ->helperText(__('filament-tma::instances.fields.uid_help'))
                    ->hintAction(Help::action('tma.demarrer.identifiant', __('filament-tma::instances.help.uid'))),
                TextInput::make('url')->label(__('filament-tma::instances.fields.base_url'))->required()->url()->maxLength(255)->columnSpanFull()
                    ->helperText(__('filament-tma::instances.fields.base_url_help'))
                    ->hintAction(Help::action('tma.demarrer.adresse', __('filament-tma::instances.help.base_url'))),
                Select::make('client_id')->label(__('filament-tma::common.client'))
                    ->visible(filled($clientModel))
                    ->options(fn (): array => $clientModel ? $clientModel::query()->orderBy(config('filament-tma.models.client_title', 'name'))->pluck(config('filament-tma.models.client_title', 'name'), (new $clientModel)->getKeyName())->all() : [])
                    ->searchable(),
                Toggle::make('is_active')->label(__('filament-tma::instances.fields.active'))->default(true),
            ]),
            Section::make(__('filament-tma::instances.sections.access'))->columns(2)->columnSpanFull()
                ->headerActions([Help::title('tma.demarrer.jetons', __('filament-tma::instances.help.tokens'))])
                ->visibleOn('edit')
                ->schema([
                    TextEntry::make('service_account')->label(__('filament-tma::instances.fields.service_account'))
                        ->state(fn (?Instance $record): string => $record?->apiUser?->email ?? '—')
                        ->helperText(__('filament-tma::instances.fields.service_account_help')),
                    TextEntry::make('token_status')->label(__('filament-tma::instances.fields.token'))
                        ->state(fn (?Instance $record): string => filled($record?->token)
                            ? __('filament-tma::instances.fields.token_received')
                            : __('filament-tma::instances.fields.token_pending'))
                        ->badge()
                        ->color(fn (?Instance $record): string => filled($record?->token) ? 'success' : 'warning'),
                    TextInput::make('token')->label(__('filament-tma::instances.fields.token_manual'))
                        ->helperText(__('filament-tma::instances.fields.token_manual_help'))
                        ->password()->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('filament-tma::instances.fields.name'))->searchable()->sortable(),
                TextColumn::make('short_name')->label(__('filament-tma::instances.columns.short_name'))->placeholder('—'),
                TextColumn::make('client.'.config('filament-tma.models.client_title', 'name'))->label(__('filament-tma::common.client'))->placeholder('—'),
                TextColumn::make('url')->label(__('filament-tma::instances.fields.base_url'))->toggleable(),
                TextColumn::make('uid')->label(__('filament-tma::instances.columns.uid'))->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label(__('filament-tma::instances.fields.active'))->boolean(),
                TextColumn::make('last_seen_at')->label(__('filament-tma::instances.columns.last_seen'))->since()->placeholder('—'),
                TextColumn::make('last_pushed_at')->label(__('filament-tma::instances.columns.last_push'))->since()->placeholder('—'),
            ])
            ->recordActions([
                self::setupClientAction(),
                self::pingAction(),
                self::usersAction(),
                EditAction::make(),
            ]);
    }

    public static function newUid(): string
    {
        return strtoupper((string) Str::ulid());
    }

    /**
     * « Configuration de l'application » : émet un jeton pour le compte de service de l'application (créé avec elle ;
     * l'ancien jeton est révoqué), puis montre la commande à lancer dans l'application. Cette commande écrit sa
     * configuration, crée le compte du Support chez elle, et lui transmet son jeton (`POST master/register`).
     */
    public static function setupClientAction(): Action
    {
        return Action::make('setupClient')
            ->label(__('filament-tma::instances.configure.label'))
            ->icon('heroicon-m-key')
            ->modalHeading(fn (Instance $record): string => __('filament-tma::instances.configure.heading', ['name' => $record->name]))
            ->modalDescription(__('filament-tma::instances.configure.description'))
            ->modalSubmitActionLabel(__('filament-tma::instances.configure.submit'))
            ->fillForm(fn (Instance $record): array => [
                'master_url' => config('filament-tma.api.public_url') ?: config('app.url'),
                'ticket_prefix' => strtoupper(Str::substr(Str::slug($record->name, ''), 0, 3)),
            ])
            ->schema([
                TextInput::make('master_url')->label(__('filament-tma::instances.configure.master_url'))->url()->required()
                    ->helperText(__('filament-tma::instances.configure.master_url_help'))
                    ->hintAction(Help::action('tma.demarrer.adresse', __('filament-tma::instances.help.base_url'))),
                TextInput::make('ticket_prefix')->label(__('filament-tma::instances.configure.prefix'))->required()->maxLength(10)
                    ->hintAction(Help::action('tma.demarrer.identifiant', __('filament-tma::instances.help.prefix'))),
            ])
            ->action(function (Instance $record, array $data, $livewire): void {
                $token = ServiceAccount::token($record->ensureServiceAccount());

                $livewire->replaceMountedAction('clientEnv', [
                    'command' => self::installCommand($record, $data['master_url'], $token, $data['ticket_prefix']),
                    'env' => EnvFile::lines(array_filter([
                        'TMA_MODE' => 'client',
                        'TMA_INSTANCE_UID' => $record->uid,
                        'TMA_INSTANCE_NAME' => $record->name,
                        'TMA_INSTANCE_SHORT_NAME' => $record->short_name,
                        'TMA_TICKET_PREFIX' => strtoupper($data['ticket_prefix']),
                        'TMA_MASTER_URL' => rtrim($data['master_url'], '/'),
                        'TMA_MASTER_TOKEN' => $token,
                        'TMA_MASTER_API_USER' => self::MASTER_ACCOUNT,
                    ], 'filled')),
                ]);
            });
    }

    /** Le compte que l'application cliente crée pour le Support (`tma:install --master-account`). */
    public const MASTER_ACCOUNT = 'support@tma.local';

    /** Ce qu'affiche « Configuration de l'application » : la commande, ou les lignes de .env. */
    public static function clientEnvAction(): Action
    {
        return Action::make('clientEnv')
            ->modalHeading(__('filament-tma::instances.configure.label'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-tma::common.close'))
            ->modalContent(fn (array $arguments) => view('filament-tma::instances.client-env', [
                'command' => $arguments['command'] ?? '',
                'env' => $arguments['env'] ?? '',
            ]));
    }

    private static function installCommand(Instance $record, string $masterUrl, string $token, string $prefix): string
    {
        return collect([
            'php artisan tma:install --mode=client',
            '--uid='.$record->uid,
            '--name='.escapeshellarg($record->name),
            filled($record->short_name) ? '--short-name='.escapeshellarg($record->short_name) : null,
            '--prefix='.escapeshellarg(strtoupper($prefix)),
            '--master-url='.escapeshellarg(rtrim($masterUrl, '/')),
            '--master-token='.$token,
            '--master-account='.self::MASTER_ACCOUNT,
        ])->filter()->implode(' ');
    }

    public static function pingAction(): Action
    {
        return Action::make('ping')
            ->label(__('filament-tma::instances.test.label'))
            ->icon('heroicon-m-signal')
            ->action(function (Instance $record): void {
                try {
                    $answer = (new InstanceClient($record))->ping();
                } catch (SyncException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                $sameUid = ($answer['instance_uid'] ?? null) === $record->uid;
                Notification::make()
                    ->{$sameUid ? 'success' : 'warning'}()
                    ->title($sameUid ? __('filament-tma::instances.test.success', ['name' => $answer['name'] ?? $record->name]) : __('filament-tma::instances.test.wrong_uid'))
                    ->send();
            });
    }

    /**
     * « Tout renvoyer » : les noms à jour (ceux du Support, ceux des correspondants de l'application), puis ses projets,
     * tous ses tickets et ses tâches publiées, par la file (`Support\Resync`, comme `tma:resync --instance`).
     */
    public static function resyncAction(): Action
    {
        return Action::make('resyncAll')
            ->label(__('filament-tma::instances.resync.label'))
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->visible(fn (Instance $record): bool => Tma::isMaster() && static::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Instance $record): string => __('filament-tma::instances.resync.heading', ['name' => $record->name]))
            ->modalDescription(fn (Instance $record): string => __('filament-tma::instances.resync.description', [
                'projects' => Resync::projects($record)->count(),
                'n' => Resync::subjects($record)->filter->isShared()->count(),
            ]))
            ->modalSubmitActionLabel(__('filament-tma::instances.resync.label'))
            ->action(function (Instance $record): void {
                $names = Resync::refreshNames($record);
                $projects = Resync::pushProjects(Resync::projects($record));
                $sent = Resync::push(Resync::subjects($record));

                Notification::make()->success()
                    ->title(__('filament-tma::common.send_requested'))
                    ->body(__('filament-tma::instances.resync.done', ['projects' => $projects, 'sent' => $sent, 'names' => $names]))
                    ->send();
            });
    }

    public static function usersAction(): Action
    {
        return Action::make('users')
            ->label(__('filament-tma::instances.users.heading'))
            ->icon('heroicon-m-users')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-tma::common.close'))
            ->modalDescription(__('filament-tma::instances.users.description'))
            ->modalContent(function (Instance $record): HtmlString {
                try {
                    $users = RemoteUsers::for($record);
                } catch (SyncException $e) {
                    return new HtmlString('<p>'.e($e->getMessage()).'</p>');
                }

                if ($users === []) {
                    return new HtmlString('<p>'.e(__('filament-tma::instances.users.empty')).'</p>');
                }

                return new HtmlString('<ul class="fi-tma-users">'.collect($users)->map(fn (array $user): string => '<li><strong>'.e($user['name']).'</strong> '.e($user['email'] ?? '').'</li>')->implode('').'</ul>');
            })
            ->extraModalFooterActions([
                Action::make('refreshUsers')
                    ->label(__('filament-tma::instances.users.refresh'))
                    ->action(fn (Instance $record) => RemoteUsers::forget($record)),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\AgentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstances::route('/'),
            'create' => CreateInstance::route('/create'),
            'edit' => EditInstance::route('/{record}/edit'),
        ];
    }
}
