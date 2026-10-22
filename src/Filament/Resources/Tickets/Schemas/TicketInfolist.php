<?php

namespace CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Schemas;

use CharlesStOlive\FilamentTma\Enums\TicketOpening;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Filament\Schemas\RecipientsSection;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketInfolist
{
    /**
     * Le volet principal de la fiche : l'historique (la demande, puis les échanges, chacun avec ses notes), et
     * dessous, à part, le nouveau message.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Historique'))
                ->columnSpanFull()
                ->headerActions([Help::title('tma.chronologie.notes', __('Messages et notes'))])
                ->schema([
                    ViewEntry::make('timeline')
                        ->hiddenLabel()
                        ->view('filament-tma::timeline.ticket'),
                ]),
            self::composerSection(),
        ]);
    }

    /** Le nouveau message, sous l'historique (`Filament\Concerns\HasComposer`). */
    public static function composerSection(): Section
    {
        return Section::make(__('Nouveau message'))
            ->columnSpanFull()
            ->headerActions([Help::title('tma.chronologie', __('Qui répond ?'))])
            ->visible(fn ($livewire): bool => method_exists($livewire, 'hasComposer') && $livewire->hasComposer())
            ->schema([EmbeddedSchema::make('composer')]);
    }

    /**
     * Le volet latéral de la fiche (voir `ViewTicket`) : où en est le ticket, et qui doit agir.
     *
     * @return array<int, Component>
     */
    public static function side(): array
    {
        return [
            Section::make(__('Suivi'))
                ->columnSpanFull()
                ->headerActions([Help::title('tma.comprendre.la-main', __('Qui doit agir ?'))])
                ->schema([
                    TextEntry::make('agent_mode')->hiddenLabel()
                        ->state(fn ($record): ?string => Context::current()->forSubject($record)->isAgent()
                            ? __('Vous intervenez ici au nom du Support.')
                            : null)
                        ->visible(fn ($record): bool => Context::current()->forSubject($record)->isAgent())
                        ->badge()->color('info')->icon('heroicon-m-lifebuoy')
                        ->hintAction(Help::action('tma.agents', __('Membre du Support'))),
                    TextEntry::make('state')->label(__('État'))->badge()
                        ->helperText(fn (Ticket $record): ?string => $record->state->getDescription()),
                    TextEntry::make('waiting_on')->label(__('En attente de'))
                        ->state(fn (Ticket $record): ?string => $record->waitingOnFor(auth()->user()))
                        ->placeholder('—'),
                    TextEntry::make('priority')->label(__('Priorité'))->badge(),
                    // « Demandeur », ou « Adressé à » quand le Support écrit au client ; au nom d'un client, qui l'a écrit.
                    TextEntry::make('requester.name')->label(fn (Ticket $record): string => $record->opening->requesterLabel())
                        ->helperText(fn (Ticket $record): ?string => match (true) {
                            $record->opening === TicketOpening::OnBehalf && $record->createdBy !== null => __('Créé par :author en son nom.', ['author' => $record->createdBy->name]),
                            $record->opening === TicketOpening::Notice => __('Pour information : pas de réponse attendue.'),
                            default => null,
                        }),
                    TextEntry::make('assignee.name')->label(__('Contact au Support'))->placeholder('—'),
                    TextEntry::make('paused_until')->label(__('Reprise le'))->dateTime('d/m/Y H:i')
                        ->visible(fn (Ticket $record): bool => $record->paused_until !== null),
                    TextEntry::make('sourceInstance.name')->label(__('Application'))
                        ->visible(fn (): bool => Tma::isMaster()),
                    TextEntry::make('task.number')->label(__('À partir de la tâche'))
                        ->formatStateUsing(fn (Ticket $record): string => $record->task->number.' — '.$record->task->title)
                        ->url(fn (Ticket $record): ?string => TaskResource::canView($record->task) ? TaskResource::getUrl('view', ['record' => $record->task]) : null)
                        ->visible(fn (Ticket $record): bool => $record->task !== null),
                    TextEntry::make('sync_status')->label(__('Synchro'))->badge()
                        ->hintAction(Help::action('tma.synchronisation.erreurs', __('Que veut dire ce statut ?')))
                        ->visible(fn (Ticket $record): bool => $record->isShared())
                        ->helperText(fn (Ticket $record): ?string => $record->last_sync_error),
                    TextEntry::make('created_at')->label(__('Créé le'))->dateTime('d/m/Y H:i'),
                    TextEntry::make('last_activity_at')->label(__('Dernière activité'))->since(),
                ]),
            RecipientsSection::make(),
        ];
    }
}
