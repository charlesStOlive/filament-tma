<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Instances\RelationManagers;

use CharlesStOlive\FilamentTma\Models\AgentLink;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\ServiceAccount;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\InstanceClient;
use CharlesStOlive\FilamentTma\Sync\SyncException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Les membres du Support dans une application cliente : des personnes de l'équipe qui ont aussi un compte
 * dans l'application (même e-mail). Les relier leur émet un jeton personnel limité à cette application, envoyé à
 * l'application : sur un ticket du Support, elles y agissent alors en leur nom d'ici — jamais comme demandeur.
 */
class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agentLinks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-tma::instances.agents.heading');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('filament-tma::instances.agents.description'))
            ->columns([
                TextColumn::make('user.name')->label(__('filament-tma::instances.agents.columns.agent')),
                TextColumn::make('user.email')->label(__('filament-tma::instances.agents.columns.email')),
                TextColumn::make('linked_at')->label(__('filament-tma::instances.agents.columns.linked_at'))->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Help::title('tma.agents', __('filament-tma::instances.agents.help')),
                Action::make('linkAgent')
                    ->label(__('filament-tma::instances.agents.link'))
                    ->icon('heroicon-m-user-plus')
                    ->modalDescription(__('filament-tma::instances.agents.link_description'))
                    ->schema([
                        Select::make('user_id')->label(__('filament-tma::instances.agents.columns.agent'))
                            ->options(fn (): array => $this->agentCandidates())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $user = Tma::userModel()::query()->findOrFail($data['user_id']);
                        $this->link($user);
                    }),
            ])
            ->recordActions([
                Action::make('relink')
                    ->label(__('filament-tma::instances.agents.resend'))
                    ->icon('heroicon-m-arrow-path')
                    ->tooltip(__('filament-tma::instances.agents.resend_tooltip'))
                    ->action(fn (AgentLink $record) => $this->link($record->user)),
                Action::make('unlink')
                    ->label(__('filament-tma::instances.agents.unlink'))
                    ->icon('heroicon-m-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AgentLink $record): void {
                        /** @var Instance $instance */
                        $instance = $this->getOwnerRecord();
                        ServiceAccount::revokeAgentToken($record->user, $instance->uid);
                        rescue(fn () => (new InstanceClient($instance))->unlinkAgent($record->user->email), report: false);
                        $record->delete();
                        Notification::make()->success()->title(__('filament-tma::instances.agents.unlinked'))->send();
                    }),
            ]);
    }

    /** Les personnes de l'équipe : des utilisateurs qui ont des rôles (jamais un compte de service). */
    private function agentCandidates(): array
    {
        $model = Tma::userModel();
        $services = Instance::query()->whereNotNull('api_user_id')->pluck('api_user_id')->all();

        return $model::query()
            ->whereNotIn((new $model)->getKeyName(), $services)
            ->when(method_exists($model, 'roles'), fn ($query) => $query->whereHas('roles'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($user): array => [$user->getAuthIdentifier() => $user->name.' — '.$user->email])
            ->all();
    }

    private function link(object $user): void
    {
        /** @var Instance $instance */
        $instance = $this->getOwnerRecord();
        $token = ServiceAccount::agentToken($user, $instance->uid);

        try {
            (new InstanceClient($instance))->linkAgent($user->email, (string) $user->getAuthIdentifier(), $user->name, $token);
        } catch (SyncException $e) {
            ServiceAccount::revokeAgentToken($user, $instance->uid);
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        AgentLink::query()->updateOrCreate(
            ['instance_id' => $instance->getKey(), 'user_id' => $user->getAuthIdentifier()],
            ['linked_at' => now()],
        );

        Notification::make()->success()->title(__('filament-tma::instances.agents.acting_in', ['name' => $user->name, 'app' => $instance->name]))->send();
    }
}
