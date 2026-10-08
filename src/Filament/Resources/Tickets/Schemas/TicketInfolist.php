<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\TimeTotals;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make(__('Demande'))
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->placeholder(__('Pas de description.'))
                            ->state(fn (Ticket $record): ?HtmlString => filled($record->description) ? new HtmlString($record->renderRichContent('description')) : null)
                            ->prose(),
                        ViewEntry::make('attachments')
                            ->hiddenLabel()
                            ->view('filament-tma::attachments')
                            ->viewData(['collection' => Ticket::ATTACHMENTS_COLLECTION]),
                    ]),
                Section::make(__('Suivi'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('state')->label(__('État'))->badge()
                            ->helperText(fn (Ticket $record): ?string => $record->state->getDescription()),
                        TextEntry::make('in_hands')->label(__('La main est à'))
                            ->state(fn (Ticket $record): ?string => $record->sideInCharge()?->getLabel())
                            ->placeholder('—'),
                        TextEntry::make('handling_mode')->label(__('Traitement'))->badge(),
                        TextEntry::make('priority')->label(__('Priorité'))->badge(),
                        TextEntry::make('requester.name')->label(__('Demandeur')),
                        TextEntry::make('assignee.name')->label(__('Intervenant'))->placeholder('—'),
                        TextEntry::make('due_on')->label(__('Échéance'))->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('paused_until')->label(__('Reprise le'))->dateTime('d/m/Y H:i')
                            ->visible(fn (Ticket $record): bool => $record->paused_until !== null),
                        TextEntry::make('time_total')->label(__('Temps passé'))
                            ->state(fn (Ticket $record): string => Duration::format(TimeTotals::for($record)['total_minutes'])),
                        TextEntry::make('sourceInstance.name')->label(__('Application'))
                            ->visible(fn (): bool => Tma::isMaster()),
                        TextEntry::make('sync_status')->label(__('Synchro'))->badge()
                            ->visible(fn (Ticket $record): bool => $record->handling_mode === HandlingMode::ServiceDesk)
                            ->helperText(fn (Ticket $record): ?string => $record->last_sync_error),
                        TextEntry::make('created_at')->label(__('Créé le'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('last_activity_at')->label(__('Dernière activité'))->since(),
                    ]),
            ]),
            Section::make(__('Chronologie'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('timeline')
                        ->hiddenLabel()
                        ->view('filament-tma::timeline.ticket'),
                ]),
        ]);
    }
}
