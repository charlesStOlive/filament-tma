<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets;

use CharlesStOlive\FilamentStateFusionEnhanced\Tables\Filters\StateFusionSelectFilter;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Enums\TicketOpening;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\EditTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketForm;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketInfolist;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Orthography;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Les tickets de TMA.
 *
 * Droits, au format de charlesstolive/filament-permission-manager (`permissions:sync` les crée), sans en dépendre :
 * - la liste : `tma.ticket.viewany`, `view`, `create`, `edit`, `delete` (policy de repli du gestionnaire) ;
 * - qui est qui : `correspond` (correspondant du Support, dans une application cliente : faire une demande, la
 *   transmettre, en être le demandeur) et `handle` (membre du Support, dans le maître : traiter les demandes) ;
 * - à côté du workflow : `note`, `time`, `sync`, `remind`, `notify` ; les forçages `force-close` et `force-return` ;
 * - avec filament-prism installé : `ai.orthography`, corriger l'orthographe avec l'IA (voir `Support\Orthography`).
 *
 * Les passages normaux ne sont pas des droits : ils suivent le tour (`Workflow\TicketWorkflow`, addendum §3). De même,
 * on ne modifie la demande qu'en en étant l'auteur, tant qu'aucun message ne l'a suivie, et le Support ne retouche le
 * titre et la priorité qu'à son tour.
 */
class TicketResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'tma/tickets';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    /** Lu par filament-permission-manager : les permissions restent `tma.ticket.*`, où que la liste soit rangée. */
    protected static ?string $permissionPrefix = 'tma.ticket';

    /** @var array<int, string> */
    protected static array $specificPermissions = [
        TmaGate::CORRESPOND, TmaGate::HANDLE, TmaGate::NOTE, TmaGate::TIME, TmaGate::SYNC, TmaGate::REMIND, TmaGate::NOTIFY,
        TmaGate::FORCE_CLOSE, TmaGate::FORCE_RETURN,
    ];

    /** @var array<string, string> Des clés de traduction : filament-permission-manager les traduit à l'affichage. */
    protected static array $permissionLabels = [
        TmaGate::CORRESPOND => 'filament-tma::permissions.ticket.correspond',
        TmaGate::HANDLE => 'filament-tma::permissions.ticket.handle',
        TmaGate::FORCE_CLOSE => 'filament-tma::permissions.ticket.force_close',
        TmaGate::FORCE_RETURN => 'filament-tma::permissions.ticket.force_return',
        TmaGate::NOTE => 'filament-tma::permissions.note',
        TmaGate::TIME => 'filament-tma::permissions.time',
        TmaGate::SYNC => 'filament-tma::permissions.sync',
        TmaGate::REMIND => 'filament-tma::permissions.ticket.remind',
        TmaGate::NOTIFY => 'filament-tma::permissions.ticket.notify',
    ];

    /** @var array<string, string> */
    protected static array $permissionFamilies = [Orthography::FAMILY => Orthography::FAMILY_LABEL];

    /** @return array<string, string> */
    public static function permissionActions(): array
    {
        return Orthography::permissionActions();
    }

    public static function getModelLabel(): string
    {
        return __('filament-tma::tickets.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-tma::tickets.model.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-tma.navigation.sort');
    }

    public static function canCreate(): bool
    {
        return auth()->user() !== null && app(TicketWorkflow::class)->mayCreate(Context::current()) && parent::canCreate();
    }

    /** Son auteur, tant qu'aucun message n'a suivi la demande ; le Support, à son tour (titre et priorité). */
    public static function canEdit(Model $record): bool
    {
        /** @var Ticket $record */
        if (auth()->user() === null) {
            return false;
        }

        $workflow = app(TicketWorkflow::class);

        return ($workflow->mayEditDemand($record, Context::current()) || $workflow->mayQualify($record, Context::current()))
            && parent::canEdit($record);
    }

    /** Un brouillon, par son auteur. */
    public static function canDelete(Model $record): bool
    {
        /** @var Ticket $record */
        return $record->state instanceof Draft
            && auth()->user() !== null
            && app(TicketWorkflow::class)->mayEditDemand($record, Context::current())
            && parent::canDelete($record);
    }

    /** Le menu d'aide de la page : les tickets, leur chronologie, et ce qu'il faut comprendre du module. */
    public static function getDocumentation(): array|string
    {
        return ['tma.tickets', 'tma.comprendre.la-main', 'tma.chronologie', 'tma.tickets.cloture-automatique', 'tma.comprendre'];
    }

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('filament-tma::common.number'))->searchable()->sortable(),
                TextColumn::make('sourceInstance.name')->label(__('filament-tma::common.application'))
                    ->visible(fn (): bool => Tma::isMaster())->toggleable(),
                TextColumn::make('title')->label(__('filament-tma::common.title'))->searchable()->limit(60)->wrap(),
                TextColumn::make('state')->label(__('filament-tma::common.state'))->badge(),
                TextColumn::make('waiting_on')->label(__('filament-tma::common.waiting_for'))->badge()->placeholder('—')
                    ->state(fn (Ticket $record): ?string => $record->waitingOnFor(auth()->user()))
                    ->color(fn (Ticket $record): string => match (true) {
                        $record->waitingOnFor(auth()->user()) === __('filament-tma::common.you') => 'warning',
                        $record->sideInCharge() === Side::Handler => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('priority')->label(__('filament-tma::common.priority'))->badge()->sortable(),
                // Quand le Support écrit au client, sa personne n'est pas demandeur : on le dit dessous.
                TextColumn::make('requester.name')->label(__('filament-tma::common.requester'))->toggleable()
                    ->description(fn (Ticket $record): ?string => match (true) {
                        $record->opening->fromSupport() => __('filament-tma::tickets.addressed_by_support'),
                        $record->opening === TicketOpening::OnBehalf => __('filament-tma::tickets.on_their_behalf'),
                        default => null,
                    }),
                TextColumn::make('assignee.name')->label(__('filament-tma::common.support_contact'))->placeholder('—')->toggleable(),
                TextColumn::make('sync_status')->label(__('filament-tma::common.sync'))->badge()
                    ->visible(fn (): bool => Tma::isMaster() || Tma::serviceDeskAvailable())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')->label(__('filament-tma::common.last_activity'))->since()->sortable(),
            ])
            ->filters([
                // Les valeurs d'office sont celles d'un raccourci de la liste (`ListTickets::shortcuts()`) : « Mes
                // demandes » dans une application cliente, « Actifs » ailleurs et pour un membre du Support.
                Filter::make('awaiting')->label(__('filament-tma::tickets.filters.waiting_for_me'))->toggle()
                    ->query(fn (Builder $query): Builder => $query->awaiting(auth()->user())),
                Filter::make('mine')->label(__('filament-tma::tickets.filters.my_requests'))->toggle()
                    ->default(fn (): bool => static::showsOwnRequestsFirst())
                    ->query(fn (Builder $query): Builder => $query->whereIn('requester_actor_id', Actor::query()
                        ->where('instance_uid', Tma::instanceUid())
                        ->where('user_id', auth()->id())
                        ->select('id'))),
                StateFusionSelectFilter::make('state')->label(__('filament-tma::common.state'))->multiple()
                    ->default(TicketState::active()),
                SelectFilter::make('priority')->label(__('filament-tma::common.priority'))->options(Priority::class)->multiple(),
                SelectFilter::make('source_instance_uid')->label(__('filament-tma::common.application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
                SelectFilter::make('sync_status')->label(__('filament-tma::common.sync'))->options(SyncStatus::class)
                    ->visible(fn (): bool => Tma::isMaster() || Tma::serviceDeskAvailable()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * Dans une application cliente, chacun voit d'abord ses propres demandes. Pas un membre du Support : il n'y
     * demande rien, il traite celles de tous.
     */
    public static function showsOwnRequestsFirst(): bool
    {
        return Tma::isClient() && Agent::for(auth()->user()) === null;
    }

    public static function getRelations(): array
    {
        return [
            TimeEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }
}
