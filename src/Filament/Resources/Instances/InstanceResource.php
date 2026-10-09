<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances;

use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Models\Instance;
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
                    ->helperText(__('Il ne change plus une fois des tickets reçus.')),
                TextInput::make('url')->label(__('Adresse'))->required()->url()->maxLength(255)->columnSpanFull(),
                Select::make('client_id')->label(__('Client'))
                    ->visible(filled($clientModel))
                    ->options(fn (): array => $clientModel ? $clientModel::query()->orderBy(config('filament-tma.models.client_title', 'name'))->pluck(config('filament-tma.models.client_title', 'name'), (new $clientModel)->getKeyName())->all() : [])
                    ->searchable(),
                Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
            Section::make(__('Accès'))->columns(2)->columnSpanFull()->schema([
                Select::make('api_user_id')->label(__('Compte de service de l’application'))
                    ->helperText(__('L’utilisateur d’ici dont l’application détient un jeton pour appeler le centre de service.'))
                    ->options(fn (): array => $userModel::query()->orderBy('name')->pluck('name', (new $userModel)->getKeyName())->all())
                    ->searchable()
                    ->unique(ignoreRecord: true),
                TextInput::make('token')->label(__('Jeton émis par l’application'))
                    ->helperText(__('Laisser vide pour garder le jeton actuel. Il est chiffré en base.'))
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
                self::pingAction(),
                self::usersAction(),
                EditAction::make(),
            ]);
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
