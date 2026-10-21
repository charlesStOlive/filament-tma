<?php

namespace CharlesStOlive\FilamentTma\Filament\Widgets;

use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\TicketState;
use CharlesStOlive\FilamentTma\Support\Terms;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use CharlesStOlive\FilamentTma\Support\Tma;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le tableau de bord : les tickets en cours (ceux de la liste « Actifs ») dont on est destinataire, principal ou
 * secondaire ; ceux qui vous attendent d'abord, puis les plus récemment actifs. Dix au plus, un lien vers la liste.
 * Affiché à qui voit les tickets.
 */
class OngoingTicketsWidget extends TableWidget
{
    /** Le nombre de tickets montrés. */
    public const LIMIT = 10;

    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        return TicketResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $total = $user === null ? 0 : static::query($user)->count();

        return $table
            ->heading(__('Mes tickets en cours'))
            ->description($total > static::LIMIT
                ? __('Les :limit premiers sur :total, ceux qui vous attendent d’abord.', ['limit' => static::LIMIT, 'total' => $total])
                : __('Ceux dont vous êtes destinataire ; ceux qui vous attendent d’abord.'))
            ->query(fn (): Builder => static::query($user)->limit(static::LIMIT))
            ->defaultSort(fn (Builder $query): Builder => static::awaitingFirst($query, $user))
            ->paginated(false)
            ->recordUrl(fn (Ticket $record): string => TicketUrl::view($record))
            ->columns([
                TextColumn::make('title')->label(__('Ticket'))
                    // Chez le Support, le nom court du client (« EDM »).
                    ->description(fn (Ticket $record): string => $record->number.(Tma::isMaster() ? ' · '.Terms::clientOrInstance($record->source_instance_uid) : ''), position: 'above')
                    ->weight(FontWeight::Medium)
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('recipient_role')->label(__('Destinataire'))->badge()
                    ->state(fn (Ticket $record): ?RecipientRole => $record->recipients
                        ->sortBy(fn (Recipient $recipient): int => $recipient->role === RecipientRole::Primary ? 0 : 1)
                        ->first()?->role),
                TextColumn::make('state')->label(__('État'))->badge(),
                TextColumn::make('waiting_on')->label(__('En attente de'))->badge()->placeholder('—')
                    ->state(fn (Ticket $record): ?string => $record->waitingOnFor($user))
                    ->color(fn (Ticket $record): string => match (true) {
                        $record->waitingOnFor($user) === __('Vous') => 'warning',
                        $record->sideInCharge() === Side::Handler => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Ouvrir'))
                    ->icon('heroicon-m-arrow-right')
                    ->button()
                    ->outlined()
                    ->size(Size::Small)
                    ->url(fn (Ticket $record): string => TicketUrl::view($record)),
            ])
            ->headerActions([
                Action::make('all')
                    ->label(__('Tous les tickets'))
                    ->link()
                    ->url(fn (): string => TicketResource::getUrl('index')),
            ])
            ->emptyStateIcon('heroicon-o-lifebuoy')
            ->emptyStateHeading(__('Aucun ticket en cours'))
            ->emptyStateDescription(__('Les tickets dont vous êtes destinataire apparaîtront ici.'));
    }

    /** Les tickets en cours (`TicketState::active()`) dont la personne est destinataire, principale ou secondaire. */
    public static function query(?Authenticatable $user): Builder
    {
        $mine = Actor::idsFor($user);

        return Ticket::query()
            ->whereIn('state', TicketState::active())
            ->whereHas('recipients', fn (Builder $query): Builder => $query->whereIn('actor_id', $mine))
            ->with(['requester', 'assignee', 'recipients' => fn ($query) => $query->whereIn('actor_id', $mine)]);
    }

    /** Ceux qui attendent la personne d'abord (`Ticket::awaiting()`), puis les plus récemment actifs. */
    protected static function awaitingFirst(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user !== null) {
            $awaiting = Ticket::query()->awaiting($user)->select('id')->toBase();
            $query->orderByRaw('case when '.$query->getModel()->getQualifiedKeyName().' in ('.$awaiting->toSql().') then 0 else 1 end', $awaiting->getBindings());
        }

        return $query->orderByDesc('last_activity_at');
    }
}
