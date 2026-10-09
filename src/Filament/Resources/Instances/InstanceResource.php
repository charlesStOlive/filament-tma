<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\EnvFile;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
        return __('application cliente');
    }

    public static function getPluralModelLabel(): string
    {
        return __('applications clientes');
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
        return ['tma.demarrer', 'tma.synchronisation'];
    }

    public static function form(Schema $schema): Schema
    {
        $userModel = Tma::userModel();
        $clientModel = config('filament-tma.models.client');

        return $schema->components([
            Section::make(__('Application'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('Nom'))->required()->maxLength(255),
                TextInput::make('uid')->label(__('Identifiant (TMA_INSTANCE_UID de l’application)'))
                    ->required()->length(26)->unique(ignoreRecord: true)
                    ->default(fn (): string => self::newUid())
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state)))
                    ->disabled(fn (?Instance $record): bool => $record !== null && $record->hasTickets())
                    ->suffixAction(
                        Action::make('generateUid')
                            ->icon('heroicon-m-arrow-path')
                            ->tooltip(__('Générer un nouvel identifiant'))
                            ->visible(fn (?Instance $record): bool => $record === null || ! $record->hasTickets())
                            ->action(fn ($set) => $set('uid', self::newUid())),
                    )
                    ->helperText(__('Proposé d’office : gardez-le, l’application le recevra par « Configuration de l’application ». Si elle en a déjà un, collez le sien. Il ne change plus une fois des tickets reçus.')),
                TextInput::make('url')->label(__('Adresse'))->required()->url()->maxLength(255)->columnSpanFull(),
                Select::make('client_id')->label(__('Client'))
                    ->visible(filled($clientModel))
                    ->options(fn (): array => $clientModel ? $clientModel::query()->orderBy(config('filament-tma.models.client_title', 'name'))->pluck(config('filament-tma.models.client_title', 'name'), (new $clientModel)->getKeyName())->all() : [])
                    ->searchable(),
                Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
            Section::make(__('Accès'))->columns(2)->columnSpanFull()->schema([
                Select::make('api_user_id')->label(__('Compte de service de l’application'))
                    ->helperText(__('L’utilisateur d’ici dont l’application détient un jeton pour appeler le centre de service. « Configuration de l’application » le crée.'))
                    ->options(fn (): array => $userModel::query()->orderBy('name')->pluck('name', (new $userModel)->getKeyName())->all())
                    ->searchable()
                    ->unique(ignoreRecord: true),
                TextInput::make('token')->label(__('Jeton émis par l’application'))
                    ->helperText(__('Celui qu’affiche `tma:install` dans l’application. Laisser vide pour garder le jeton actuel ; il est chiffré en base.'))
                    ->password()->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Nom'))->searchable()->sortable(),
                TextColumn::make('client.'.config('filament-tma.models.client_title', 'name'))->label(__('Client'))->placeholder('—'),
                TextColumn::make('url')->label(__('Adresse'))->toggleable(),
                TextColumn::make('uid')->label(__('Identifiant'))->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
                TextColumn::make('last_seen_at')->label(__('Dernier appel reçu'))->since()->placeholder('—'),
                TextColumn::make('last_pushed_at')->label(__('Dernier envoi'))->since()->placeholder('—'),
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
     * « Configuration de l'application » : crée (ou reprend) le compte par lequel l'application appellera le centre de
     * service, lui émet un jeton (l'ancien est révoqué), puis montre la commande à lancer dans l'application.
     */
    public static function setupClientAction(): Action
    {
        return Action::make('setupClient')
            ->label(__('Configuration de l’application'))
            ->icon('heroicon-m-key')
            ->modalHeading(fn (Instance $record): string => __('Configurer :name', ['name' => $record->name]))
            ->modalDescription(__('Un nouveau jeton est émis pour l’application ; le précédent ne marche plus. Elle devra recevoir cette configuration.'))
            ->modalSubmitActionLabel(__('Générer'))
            ->fillForm(fn (Instance $record): array => [
                'account_email' => $record->apiUser?->email ?? Str::slug($record->name).'@tma.local',
                'master_url' => config('app.url'),
                'ticket_prefix' => strtoupper(Str::substr(Str::slug($record->name, ''), 0, 3)),
            ])
            ->schema([
                TextInput::make('account_email')->label(__('Compte de service, ici'))->email()->required()
                    ->helperText(__('Créé s’il n’existe pas, sans rôle : il n’entre dans aucun écran.')),
                TextInput::make('master_url')->label(__('Adresse du centre de service, vue de l’application'))->url()->required()
                    ->helperText(__('En développement, l’adresse du conteneur (http://filbreeze.test…), pas localhost.')),
                TextInput::make('ticket_prefix')->label(__('Préfixe des tickets de l’application'))->required()->maxLength(10),
            ])
            ->action(function (Instance $record, array $data, $livewire): void {
                $user = ServiceAccount::user($data['account_email'], __('Application :name (TMA)', ['name' => $record->name]));

                if (Instance::query()->where('api_user_id', $user->getAuthIdentifier())->whereKeyNot($record->getKey())->exists()) {
                    Notification::make()->danger()->title(__('Ce compte sert déjà à une autre application.'))->send();

                    return;
                }

                $token = ServiceAccount::token($user);
                $record->update(['api_user_id' => $user->getAuthIdentifier()]);

                $livewire->replaceMountedAction('clientEnv', [
                    'command' => self::installCommand($record, $data['master_url'], $token, $data['ticket_prefix']),
                    'env' => EnvFile::lines([
                        'TMA_MODE' => 'client',
                        'TMA_INSTANCE_UID' => $record->uid,
                        'TMA_INSTANCE_NAME' => $record->name,
                        'TMA_TICKET_PREFIX' => strtoupper($data['ticket_prefix']),
                        'TMA_MASTER_URL' => rtrim($data['master_url'], '/'),
                        'TMA_MASTER_TOKEN' => $token,
                        'TMA_MASTER_API_USER' => self::MASTER_ACCOUNT,
                    ]),
                ]);
            });
    }

    /** Le compte que l'application cliente crée pour le centre de service (`tma:install --master-account`). */
    public const MASTER_ACCOUNT = 'centre-de-service@tma.local';

    /** Ce qu'affiche « Configuration de l'application » : la commande, ou les lignes de .env. */
    public static function clientEnvAction(): Action
    {
        return Action::make('clientEnv')
            ->modalHeading(__('Configuration de l’application'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Fermer'))
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
            '--prefix='.escapeshellarg(strtoupper($prefix)),
            '--master-url='.escapeshellarg(rtrim($masterUrl, '/')),
            '--master-token='.$token,
            '--master-account='.self::MASTER_ACCOUNT,
        ])->implode(' ');
    }

    public static function pingAction(): Action
    {
        return Action::make('ping')
            ->label(__('Tester'))
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
                    ->title($sameUid ? __('Connexion réussie : :name', ['name' => $answer['name'] ?? $record->name]) : __('L’application répond, mais son identifiant n’est pas celui enregistré ici.'))
                    ->send();
            });
    }

    public static function usersAction(): Action
    {
        return Action::make('users')
            ->label(__('Utilisateurs'))
            ->icon('heroicon-m-users')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Fermer'))
            ->modalDescription(__('Les utilisateurs de l’application qui ont accès au centre de service (liste gardée une heure).'))
            ->modalContent(function (Instance $record): HtmlString {
                try {
                    $users = RemoteUsers::for($record);
                } catch (SyncException $e) {
                    return new HtmlString('<p>'.e($e->getMessage()).'</p>');
                }

                if ($users === []) {
                    return new HtmlString('<p>'.e(__('Aucun utilisateur.')).'</p>');
                }

                return new HtmlString('<ul class="fi-tma-users">'.collect($users)->map(fn (array $user): string => '<li><strong>'.e($user['name']).'</strong> '.e($user['email'] ?? '').'</li>')->implode('').'</ul>');
            })
            ->extraModalFooterActions([
                Action::make('refreshUsers')
                    ->label(__('Rafraîchir'))
                    ->action(fn (Instance $record) => RemoteUsers::forget($record)),
            ]);
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
