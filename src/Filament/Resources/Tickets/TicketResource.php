<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets;

use CharlesStOlive\FilamentStateFusionEnhanced\Tables\Filters\StateFusionSelectFilter;
use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Enums\Priority;
use CharlesStOlive\FilamentTma\Enums\SyncStatus;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\EditTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketForm;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas\TicketInfolist;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\Draft;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Move;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Les tickets de TMA.
 *
 * Droits, au format de charlesstolive/filament-permission-manager (`permissions:sync` les crée), sans en dépendre :
 * - la liste : `tma.ticket.viewany`, `view`, `create`, `edit`, `delete` (policy de repli du gestionnaire) ;
 * - `$specificPermissions` : `comment`, `note` (lire et écrire les notes internes), `share-note`, `time`, `sync` ;
 * - la famille `workflow` : un droit par mouvement, `tma.ticket.workflow.take-charge`… (`TmaGate`).
 *
 * Qui a la main compte aussi, et ne dépend d'aucun droit : on ne modifie un ticket qu'en brouillon ou quand on en
 * est l'intervenant et qu'il est à nous (`Ticket::fieldsEditableHere()`).
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $slug = 'tma/tickets';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    /** Lu par filament-permission-manager : les permissions restent `tma.ticket.*`, où que la liste soit rangée. */
    protected static ?string $permissionPrefix = 'tma.ticket';

    /** @var array<int, string> */
    protected static array $specificPermissions = [TmaGate::COMMENT, TmaGate::NOTE, TmaGate::SHARE_NOTE, TmaGate::TIME, TmaGate::SYNC];

    /** @var array<string, string> */
    protected static array $permissionFamilies = [TmaGate::WORKFLOW_FAMILY => 'Passages du ticket'];

    /** @var array<string, string> */
    protected static array $permissionLabels = [
        TmaGate::COMMENT => 'Commenter et répondre',
        TmaGate::NOTE => 'Lire et écrire les notes internes',
        TmaGate::SHARE_NOTE => 'Partager une note avec l’autre partie',
        TmaGate::TIME => 'Saisir et modifier du temps (maître)',
        TmaGate::SYNC => 'Relancer une synchronisation en erreur (maître)',
    ];

    /**
     * Un droit par mouvement du workflow (ceux du planificateur exclus).
     *
     * @return array<string, string>
     */
    public static function permissionActions(): array
    {
        return collect(app(TicketWorkflow::class)->userMoves())
            ->mapWithKeys(fn (Move $move): array => [TmaGate::workflowAction($move->key()) => $move->label()])
            ->all();
    }

    public static function getModelLabel(): string
    {
        return __('ticket');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tickets');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tma.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-tma.navigation.sort');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var Ticket $record */
        return $record->fieldsEditableHere() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var Ticket $record */
        return $record->state instanceof Draft
            && $record->fieldsEditableHere()
            && parent::canDelete($record);
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
                TextColumn::make('number')->label(__('N°'))->searchable()->sortable(),
                TextColumn::make('sourceInstance.name')->label(__('Application'))
                    ->visible(fn (): bool => Tma::isMaster())->toggleable(),
                TextColumn::make('title')->label(__('Titre'))->searchable()->limit(60)->wrap(),
                TextColumn::make('state')->label(__('État'))->badge(),
                TextColumn::make('handling_mode')->label(__('Traitement'))->badge()->toggleable(),
                TextColumn::make('priority')->label(__('Priorité'))->badge()->sortable(),
                TextColumn::make('requester.name')->label(__('Demandeur'))->toggleable(),
                TextColumn::make('assignee.name')->label(__('Intervenant'))->placeholder('—')->toggleable(),
                TextColumn::make('due_on')->label(__('Échéance'))->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sync_status')->label(__('Synchro'))->badge()
                    ->visible(fn (): bool => Tma::isMaster() || Tma::serviceDeskAvailable())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_activity_at')->label(__('Dernière activité'))->since()->sortable(),
            ])
            ->filters([
                StateFusionSelectFilter::make('state')->label(__('État'))->multiple(),
                SelectFilter::make('handling_mode')->label(__('Traitement'))->options(HandlingMode::class),
                SelectFilter::make('priority')->label(__('Priorité'))->options(Priority::class)->multiple(),
                SelectFilter::make('source_instance_uid')->label(__('Application'))
                    ->visible(fn (): bool => Tma::isMaster())
                    ->options(fn (): array => Instance::query()->orderBy('name')->pluck('name', 'uid')->all()),
                SelectFilter::make('sync_status')->label(__('Synchro'))->options(SyncStatus::class)
                    ->visible(fn (): bool => Tma::isMaster() || Tma::serviceDeskAvailable()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
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
